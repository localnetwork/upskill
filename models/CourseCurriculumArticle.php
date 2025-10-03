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

        // 🔹 Always set curriculum resource type to article
        R::exec(
            'UPDATE course_curriculums SET curriculum_resource_type = ? WHERE id = ?',
            ['article', $data['curriculum_id']]
        );

        // 🔹 Check if article already exists for this curriculum
        $existingArticle = R::findOne('course_curriculum_articles', 'curriculum_id = ?', [$data['curriculum_id']]);

        if ($existingArticle) {
            // Update existing record
            R::exec(
                'UPDATE course_curriculum_articles 
             SET content = ?, author_id = ?, updated_at = ? 
             WHERE curriculum_id = ?',
                [
                    $data['content'],
                    $currentUser->user->id,
                    R::isoDateTime(),
                    $data['curriculum_id']
                ]
            );

            $message = 'Article updated successfully.';
            $status  = 200;
        } else {
            // Insert new record
            R::exec(
                'INSERT INTO course_curriculum_articles 
            (uuid, content, author_id, curriculum_id, created_at, updated_at) 
            VALUES (?,?,?,?,?,?)',
                [
                    Uuid::uuid4()->toString(),
                    $data['content'],
                    $currentUser->user->id,
                    $data['curriculum_id'],
                    R::isoDateTime(),
                    R::isoDateTime()
                ]
            );

            $message = 'Article added to curriculum successfully.';
            $status  = 201;
        }

        return [
            'status'  => $status,
            'message' => $message,
            'data'    => [
                'curriculum' => CourseCurriculum::getCurriculumById($data['curriculum_id']),
                'asset' => [
                    'content'       => $data['content'],
                    'author_id'     => $currentUser->user->id,
                    'curriculum_id' => $data['curriculum_id'],
                ]
            ]
        ];
    }


    public static function getArticleByCurriculumId($id)
    {
        return R::findOne('course_curriculum_articles', 'curriculum_id = ?', [$id]);
    }

    public static function delete($curriculumId)
    {

        $article = R::findOne('course_curriculum_articles', 'curriculum_id = ?', [$curriculumId]);
        if (!$article) {
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Article not found.'
            ];
        }

        R::exec(
            'UPDATE course_curriculums SET curriculum_resource_type = NULL WHERE id = ?',
            bindings: [$curriculumId]
        );

        R::exec('DELETE FROM course_curriculum_articles WHERE curriculum_id = ?', [$curriculumId]);

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
            $query = "SELECT COUNT(*) as total 
                  FROM course_curriculum_articles
                  WHERE curriculum_id IN ($placeholders)";

            $total = R::getCell($query, $curriculumIds);

            return (int) $total;
        } catch (\Exception $e) {
            return 0; // or handle via your error response
        }
    }
}
