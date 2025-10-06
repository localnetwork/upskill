<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../models/CourseCurriculumVideo.php';

require_once __DIR__ . '/../models/CourseCurriculumArticle.php';

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class CourseCurriculum
{
    /**  
     * 🔹 Centralized method to attach resources by type
     */
    private static function _attachResourceData(array $curriculum): array
    {
        switch ($curriculum['curriculum_resource_type'] ?? null) {
            case 'video':
                $curriculum['asset'] = CourseCurriculumVideo::getVideosByCurriculumId($curriculum['id']);
                break;

            case 'article':
                $curriculum['asset'] = CourseCurriculumArticle::getArticleByCurriculumId($curriculum['id']);
                break;

            case 'quiz':
                $curriculum['asset'] = null; // placeholder for future Quiz model
                break;

            case 'coding_exercise':
                $curriculum['asset'] = null; // placeholder for coding exercise model
                break;

            default:
                // Always return consistent structure  
                $curriculum['asset'] = null;
                break;
        }

        return $curriculum;
    }

    public static function createCurriculum($data)
    {
        $currentUser = AuthController::getCurrentUser();

        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'title'             => 'required|min:3|max:60',
            'description'       => 'required|min:3|max:5000',
            'course_section_id' => 'required|integer',
            'curriculum_type'   => 'required|in:lecture,article,quiz,coding_exercise',
            'published'         => 'boolean'
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

        try {
            $uuid       = Uuid::uuid4()->toString();
            $title      = trim($data['title']);
            $desc       = trim($data['description']);
            $sectionId  = (int) $data['course_section_id'];
            $type       = trim($data['curriculum_type']);
            $published  = isset($data['published']) ? (int)$data['published'] : 1;
            $authorId   = $currentUser->user->id;
            $now        = date('Y-m-d H:i:s');

            $section = CourseSection::getSectionById($sectionId);
            if ($section['error']) {
                return [
                    'error'   => true,
                    'status'  => 404,
                    'message' => 'Course section not found.'
                ];
            }

            R::exec(
                'INSERT INTO course_curriculums 
                 (uuid, title, curriculum_description, published, author_id, 
                  course_section_id, curriculum_type, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuid,
                    $title,
                    $desc,
                    $published,
                    $authorId,
                    $sectionId,
                    $type,
                    $now,
                    $now
                ]
            );

            $curriculum = R::getRow(
                'SELECT * FROM course_curriculums WHERE uuid = ?',
                [$uuid]
            );

            // 🔹 Attach resource data before returning
            $curriculum = self::_attachResourceData($curriculum);

            return [
                'error'   => false,
                'status'  => 201,
                'message' => 'Course curriculum created successfully.',
                'data'    => $curriculum
            ];
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public static function getCurriculumsBySectionIds(array $sectionIds)
    {
        if (empty($sectionIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));

            $rows = R::getAll(
                "SELECT * FROM course_curriculums 
             WHERE course_section_id IN ($placeholders)
             ORDER BY sort_order ASC, created_at ASC",
                $sectionIds
            );

            foreach ($rows as &$curriculum) {
                $curriculum = self::_attachResourceData($curriculum);
            }

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row['course_section_id']][] = $row;
            }

            return $grouped;
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public static function getCurriculumsDataBySectionIds(array $sectionIds)
    {
        if (empty($sectionIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));

            $rows = R::getAll(
                "SELECT * FROM course_curriculums  
             WHERE course_section_id IN ($placeholders)
             ORDER BY sort_order ASC, created_at ASC",
                $sectionIds
            );

            // ✅ Group curriculums by section_id
            $grouped = [];
            foreach ($rows as $row) {
                $sectionId = $row['course_section_id'];
                if (!isset($grouped[$sectionId])) {
                    $grouped[$sectionId] = [];
                }
                $grouped[$sectionId][] = $row;
            }

            return $grouped;
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }


    public static function getCurriculumsBySectionId(int $sectionId)
    {
        if (empty($sectionId)) {
            return [];
        }

        try {
            $rows = R::getAll(
                "SELECT * FROM course_curriculums 
             WHERE course_section_id = ? 
             ORDER BY sort_order ASC, created_at ASC",
                [$sectionId]
            );

            foreach ($rows as &$curriculum) {
                $curriculum = self::_attachResourceData($curriculum);
            }

            return $rows;
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }


    public static function updateCurriculumById($id, $data)
    {
        $currentUser = AuthController::getCurrentUser();

        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'title'             => 'required|min:3|max:60',
            'description'       => 'required|min:3|max:5000',
            'curriculum_type'   => 'required|in:lecture,article,quiz,coding_exercise',
            'published'         => 'boolean'
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

        try {
            $curriculum = R::findOne('course_curriculums', 'id = ?', [$id]);
            if (!$curriculum) {
                return [
                    'error'   => true,
                    'status'  => 404,
                    'message' => 'Course curriculum not found.'
                ];
            }

            if ($curriculum->author_id != $currentUser->user->id) {
                return [
                    'error'   => true,
                    'status'  => 403,
                    'message' => 'You do not have permission to update this curriculum.'
                ];
            }

            $fields = [];
            $params = [];

            if (isset($data['title'])) {
                $fields[] = 'title = ?';
                $params[] = trim($data['title']);
            }
            if (isset($data['description'])) {
                $fields[] = 'curriculum_description = ?';
                $params[] = trim($data['description']);
            }
            if (isset($data['course_section_id'])) {
                $sectionId = (int) $data['course_section_id'];
                $section = CourseSection::getSectionById($sectionId);
                if ($section['error']) {
                    return [
                        'error'   => true,
                        'status'  => 404,
                        'message' => 'Course section not found.'
                    ];
                }
                $fields[] = 'course_section_id = ?';
                $params[] = $sectionId;
            }

            if (!empty($fields)) {
                R::exec(
                    "UPDATE course_curriculums SET " . implode(', ', $fields) . " WHERE id = ?",
                    array_merge($params, [$id])
                );
            }

            return [
                'error'   => false,
                'status'  => 200,
                'message' => 'Course curriculum updated successfully.'
            ];
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public static function updateCurriculumResourceType($id, $data)
    {
        $currentUser = AuthController::getCurrentUser();

        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'curriculum_resource_type' => 'required|in:video,article,coding,multiple_choice,fill_blanks',
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

        // TODO: implement update logic here if needed
    }

    public static function getCurriculumById($id)
    {
        $curriculum = R::getRow(
            'SELECT * FROM course_curriculums WHERE id = ?',
            [$id]
        );

        if (!$curriculum) {
            return null;
        }

        return self::_attachResourceData($curriculum);
    }

    public static function deleteCurriculumById($id)
    {
        $curriculum = R::load('course_curriculums', $id);

        if (!$curriculum->id) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Curriculum not found.'
            ];
        }

        R::trash($curriculum);

        return [
            'error'   => false,
            'status'  => 200,
            'message' => 'Curriculum deleted successfully.'
        ];
    }

    public static function sort($items, $sectionId)
    {
        echo json_encode($items);

        if (empty($items) || empty($sectionId)) {
            return [
                'error'   => true,
                'status'  => 400,
                'message' => 'Invalid input data.'
            ];
        }

        try {
            $now = date('Y-m-d H:i:s');
            foreach ($items as $index => $curriculumId) {
                R::exec(
                    'UPDATE course_curriculums 
                 SET sort_order = ?, updated_at = ? 
                 WHERE id = ? AND course_section_id = ?',
                    [
                        $index + 1,
                        $now,
                        $curriculumId,
                        $sectionId
                    ]
                );
            }

            return [
                'error'   => false,
                'status'  => 200,
                'message' => 'Curriculums sorted successfully.'
            ];
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public static function getCurriculumResources($sectionIds)
    {
        $extractCurriculumIds = R::getCol(
            'SELECT id FROM course_curriculums WHERE course_section_id IN (' . implode(',', array_fill(0, count($sectionIds), '?')) . ')',
            $sectionIds
        );

        $videoCounts   = CourseCurriculumVideo::getCurriculumVideoCounts($extractCurriculumIds);
        $articleCounts = CourseCurriculumArticle::getCurriculumArticleCounts($extractCurriculumIds);

        return [
            'video_counts'   => $videoCounts,
            'article_counts' => $articleCounts
        ];
    }
}
