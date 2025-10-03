<?php


require_once __DIR__ . '/../models/CourseCurriculumArticle.php';

class CourseCurriculumArticleController
{
    public static function addArticleToCurriculum()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = CourseCurriculumArticle::create($data);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors'  => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        http_response_code($result['status'] ?? 200);
        echo json_encode($result);
    }

    public static function deleteArticleFromCurriculum($id)
    {
        $result = CourseCurriculumArticle::delete($id);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors'  => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        http_response_code($result['status'] ?? 200);
        echo json_encode($result);
    }
}
