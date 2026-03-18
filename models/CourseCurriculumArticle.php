<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/CourseCurriculum.php';

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class CourseCurriculumArticle
{
    /**
     * Calculate estimated read time based on word count
     */
    private static function calculateReadTime($htmlContent)
    {
        // Remove HTML tags
        $text = strip_tags($htmlContent);

        // Count words
        $wordCount = str_word_count($text);

        // Average reading speed 
        $wpm = 180;

        $minutes = ceil($wordCount / $wpm);

        return max(1, $minutes);
    }

    public static function changeCourseUpdatedAt($curriculumId)
    {
        R::exec(
            'UPDATE courses 
             JOIN course_sections ON courses.id = course_sections.course_id 
             JOIN course_curriculums ON course_sections.id = course_curriculums.course_section_id 
             SET courses.updated_at = ? 
             WHERE course_curriculums.id = ?',
            [R::isoDateTime(), $curriculumId]

        );
    }

    public static function create($data)
    {
        $currentUser = AuthController::getCurrentUser();

        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'content'       => 'required',
            'curriculum_id' => 'required|integer',
        ]);

        $validation->validate();

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        $curriculum = R::findOne('course_curriculums', 'id = ?', [$data['curriculum_id']]);

        if (!$curriculum) {
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Curriculum not found.'
            ];
        }

        /**
         * Sanitize TinyMCE content
         */
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,h1,h2,h3,h4,h5,h6,span,strong,b,i,u,em,a[href|target],ul,ol,li,blockquote,br');
        $config->set('HTML.Nofollow', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        $purifier = new \HTMLPurifier($config);

        $cleanContent = $purifier->purify($data['content']);

        /**
         * Calculate read time
         */
        $estimatedReadTime = self::calculateReadTime($cleanContent);


        $curriculumId = $data['curriculum_id'];


        self::changeCourseUpdatedAt($curriculumId);

        // // Get section_id from curriculum
        // $sectionId = R::getCell(
        //     'SELECT course_section_id FROM course_curriculums WHERE id = ?',
        //     [$curriculumId]
        // );


        // // Get course_id from course_sections
        // $courseId = R::getCell(
        //     'SELECT course_id FROM course_sections WHERE id = ?',
        //     [$sectionId]
        // );

        // Update course 
        // R::exec(
        //     'UPDATE courses SET updated_at = ? WHERE id = ?',
        //     [R::isoDateTime(), $courseId]
        // );

        /**
         * Set curriculum resource type
         */
        R::exec(
            'UPDATE course_curriculums SET curriculum_resource_type = ? WHERE id = ?',
            ['article', $data['curriculum_id']]
        );

        /**
         * Check existing article
         */
        $existingArticle = R::findOne(
            'course_curriculum_articles',
            'curriculum_id = ?',
            [$data['curriculum_id']]
        );

        if ($existingArticle) {

            R::exec(
                'UPDATE course_curriculum_articles 
                 SET content = ?, author_id = ?, estimated_duration = ?, updated_at = ? 
                 WHERE curriculum_id = ?',
                [
                    $cleanContent,
                    $currentUser->user->id,
                    $estimatedReadTime,
                    R::isoDateTime(),
                    $data['curriculum_id']
                ]
            );

            $message = 'Article updated successfully.';
            $status = 200;
        } else {

            R::exec(
                'INSERT INTO course_curriculum_articles 
                 (uuid, content, author_id, curriculum_id, estimated_duration, created_at, updated_at) 
                 VALUES (?,?,?,?,?,?,?)',
                [
                    Uuid::uuid4()->toString(),
                    $cleanContent,
                    $currentUser->user->id,
                    $data['curriculum_id'],
                    $estimatedReadTime,
                    R::isoDateTime(),
                    R::isoDateTime()
                ]
            );

            $message = 'Article added to curriculum successfully.';
            $status = 201;
        }

        return [
            'status'  => $status,
            'message' => $message,
            'data'    => [
                'curriculum' => CourseCurriculum::getCurriculumById($data['curriculum_id']),
                'asset' => [
                    'content'            => $cleanContent,
                    'author_id'          => $currentUser->user->id,
                    'curriculum_id'      => $data['curriculum_id'],
                    'estimated_duration' => $estimatedReadTime
                ]
            ]
        ];
    }

    public static function getArticleByCurriculumId($id)
    {
        return R::findOne(
            'course_curriculum_articles',
            'curriculum_id = ?',
            [$id]
        );
    }

    public static function getArticleDurationByCurriculumId($id)
    {
        $article = self::getArticleByCurriculumId($id);
        return $article ? $article->estimated_duration : "null";
    }

    public static function delete($curriculumId)
    {
        $article = R::findOne(
            'course_curriculum_articles',
            'curriculum_id = ?',
            [$curriculumId]
        );

        if (!$article) {
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Article not found.'
            ];
        }

        R::exec(
            'UPDATE course_curriculums 
             SET curriculum_resource_type = NULL 
             WHERE id = ?',
            [$curriculumId]
        );

        R::exec(
            'UPDATE course SET updated_at = ? WHERE id = ?',
            [R::isoDateTime(), $article->curriculum->course_id]
        );

        R::exec(
            'DELETE FROM course_curriculum_articles  
             WHERE curriculum_id = ?',
            [$curriculumId]
        );



        return [
            'status'  => 200,
            'message' => 'Article deleted successfully.',
            'data' => [
                'curriculum' => CourseCurriculum::getCurriculumById($curriculumId)
            ]
        ];
    }

    public static function getCurriculumArticleCounts($curriculumIds)
    {
        try {

            if (empty($curriculumIds) || !is_array($curriculumIds)) {
                return 0;
            }

            $placeholders = implode(',', array_fill(0, count($curriculumIds), '?'));

            $query = "
                SELECT COUNT(*) as total
                FROM course_curriculum_articles
                WHERE curriculum_id IN ($placeholders)
            ";

            $total = R::getCell($query, $curriculumIds);

            return (int) $total;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
