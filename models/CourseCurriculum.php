<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../models/CourseSection.php';



use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class CourseCurriculum
{
    public static function createCurriculum($data)
    {
        $currentUser = AuthController::getCurrentUser();

        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'title'             => 'required|min:3|max:60',
            'description'       => 'required|min:3|max:255',
            'course_section_id' => 'required|integer',
            'type'              => 'required|in:lecture,article,quiz,coding',
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
            $type       = trim($data['type']); // map to curriculum_type
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


            // ✅ Insert matching schema
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

            // ✅ Fetch the inserted record
            $curriculum = R::getRow(
                'SELECT * FROM course_curriculums WHERE uuid = ?',
                [$uuid]
            );

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
            // Build placeholders for IN clause (?,?,?)
            $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));

            // Fetch all curriculums for these section IDs
            $rows = R::getAll(
                "SELECT * FROM course_curriculums 
             WHERE course_section_id IN ($placeholders)
             ORDER BY created_at ASC",
                $sectionIds
            );

            // Group by section_id for easy mapping
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

    public static function getCurriculumsBySectionId(int $sectionId)
    {
        if (empty($sectionId)) {
            return [];
        }

        try {
            // Fetch all curriculums for this section ID
            $rows = R::getAll(
                "SELECT * FROM course_curriculums 
             WHERE course_section_id = ?
             ORDER BY created_at ASC",
                [$sectionId]
            );

            return $rows;
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
