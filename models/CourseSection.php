<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/../models/Media.php';
require_once __DIR__ . '/../models/CourseLevel.php';
require_once __DIR__ . '/../models/CourseGoal.php';

require_once __DIR__ . '/../models/CourseCurriculum.php';

use Ramsey\Uuid\Uuid;
use Rakit\Validation\Validator;
use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade  

class CourseSection
{
    /**
     * Create a new course section
     */
    public static function create(array $data)
    {
        $currentUser = AuthController::getCurrentUser();

        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'title'       => 'required|min:3|max:60',
            'description' => 'required|min:3|max:255',
            'course_id'   => 'required|integer'
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
            $uuid      = \Ramsey\Uuid\Uuid::uuid4()->toString();
            $title     = trim($data['title']);
            $desc      = trim($data['description']);
            $course_id = (int) $data['course_id'];
            $now       = date('Y-m-d H:i:s');

            // ✅ Insert using raw SQL
            R::exec(
                'INSERT INTO course_sections (uuid, title, section_description, course_id, created_at, author_id)
             VALUES (?, ?, ?, ?, ?, ?)',
                [$uuid, $title, $desc, $course_id, $now, $currentUser->user->id]
            );

            // ✅ Get the inserted record
            $section = R::getRow(
                'SELECT * FROM course_sections WHERE uuid = ?',
                [$uuid]
            );

            return [
                'error'   => false,
                'status'  => 201,
                'message' => 'Course section created successfully.',
                'data'    => $section
            ];
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public static function getSectionsByCourseId(int $courseId)
    {
        $sectionsCollection = R::findAll(
            'course_sections',
            'course_id = ? ORDER BY created_at ASC',
            [$courseId]
        );

        // Convert to array
        $sections = R::exportAll($sectionsCollection);

        // ✅ Pass all section IDs to fetch their curriculums
        $sectionIds = array_column($sections, 'id');
        $curriculums = CourseCurriculum::getCurriculumsBySectionIds($sectionIds);

        // Attach curriculums to each section for easy use
        foreach ($sections as &$section) {
            $section['curriculums'] = $curriculums[$section['id']] ?? [];
        }

        return $sections;
    }

    public static function getSectionsDataByCourseId(int $courseId)
    {
        $sectionsCollection = R::findAll(
            'course_sections',
            'course_id = ? ORDER BY created_at ASC',
            [$courseId]
        );

        // Convert to array
        $sections = R::exportAll($sectionsCollection);

        // ✅ Pass all section IDs to fetch their curriculums
        $sectionIds = array_column($sections, 'id');
        $curriculums = CourseCurriculum::getCurriculumsDataBySectionIds($sectionIds);

        // Attach curriculums to each section for easy use 
        foreach ($sections as &$section) {
            $section['curriculums'] = $curriculums[$section['id']] ?? [];
        }

        return $sections;
    }

    public static function updateSectionById(int $id, array $data)
    {
        // Implementation for updating a course section  
        $section = R::load('course_sections', $id);

        if (!$section->id) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Section not found.'
            ];
        }

        // $currentUser = AuthController::getCurrentUser(); 

        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'title'       => 'required|min:3|max:60',
            'description' => 'required|min:3|max:255',
            'course_id'   => 'required|integer'
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

        R::exec(
            'UPDATE course_sections SET title = ?, section_description = ?, course_id = ?, updated_at = ? WHERE id = ?',
            [
                trim($data['title']),
                trim($data['description']),
                (int) $data['course_id'],
                date('Y-m-d H:i:s'),
                $id
            ]
        );

        return [
            'error'   => false,
            'status'  => 200,
            'message' => 'Section updated successfully.',
            'data'    => [
                'id' => $id,
                'title' => trim($data['title']),
                'section_description' => trim($data['description']),
                'course_id' => (int) $data['course_id'],
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    public static function deleteSectionById($id)
    {
        // Implementation for deleting a course section 
        $section = R::load('course_sections', $id);

        if (!$section->id) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Section not found.'
            ];
        }
        R::trash($section);
        return [
            'error'   => false,
            'status'  => 200,
            'message' => 'Section deleted successfully.'
        ];
    }

    public static function getSectionById($id)
    {
        // Load the section bean
        $section = R::load('course_sections', $id);

        // Check if section exists (id = 0 means not found in RedBean)
        if ((int)$section->id === 0) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Section not found.'
            ];
        }

        // ✅ Use your single-ID function to fetch curriculums
        $curriculums = CourseCurriculum::getCurriculumsBySectionId((int)$section->id);

        // Convert RedBean bean to array
        $sectionArray = $section->export();
        // Attach curriculums directly
        $sectionArray['curriculums'] = is_array($curriculums) ? $curriculums : [];

        return [
            'error'  => false,
            'status' => 200,
            'data'   => $sectionArray
        ];
    }

    public static function getCourseSectionCount($courseId)
    {
        try {
            // Sections
            $sectionCount = (int) R::getCell(
                'SELECT COUNT(*) FROM course_sections WHERE course_id = ?',
                [$courseId]
            );

            // Section IDs
            $sectionIds = R::getCol(
                'SELECT id FROM course_sections WHERE course_id = ?',
                [$courseId]
            );

            if (empty($sectionIds)) {
                return [
                    'section_count'    => $sectionCount,
                    'curriculum_count' => 0,
                    'video_count'      => 0,
                    'article_count'    => 0,
                ];
            }

            // Curriculums
            $curriculumIds = R::getCol(
                'SELECT id FROM course_curriculums WHERE course_section_id IN (' .
                    R::genSlots($sectionIds) . ')',
                $sectionIds
            );

            $curriculumCount = count($curriculumIds);

            if (empty($curriculumIds)) {
                return [
                    'section_count'    => $sectionCount,
                    'curriculum_count' => $curriculumCount,
                    'video_count'      => 0,
                    'article_count'    => 0,
                ];
            }

            // Videos
            $videoCount = (int) R::getCell(
                'SELECT COUNT(*) FROM course_curriculum_videos WHERE curriculum_id IN (' .
                    R::genSlots($curriculumIds) . ')',
                $curriculumIds
            );

            // Articles
            $articleCount = (int) R::getCell(
                'SELECT COUNT(*) FROM course_curriculum_articles WHERE curriculum_id IN (' .
                    R::genSlots($curriculumIds) . ')',
                $curriculumIds
            );

            return [
                'section_count'    => $sectionCount,
                'curriculum_count' => $curriculumCount,
                'video_count'      => $videoCount,
                'article_count'    => $articleCount,
            ];
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }
}
