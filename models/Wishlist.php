<?php


require_once __DIR__ . '/../controllers/AuthController.php';

class Wishlist
{

    public static function create($data)
    {
        $currentUser = AuthController::getCurrentUser();



        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($data, [
            'course_id' => 'required|integer',
        ]);

        $validation->validate();

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.',
            ];
        }

        $course = R::findOne('courses', ' id = ? AND published = ? ', [$data['course_id'], 1]);
        if (!$course) {
            return [
                'error'   => true,
                'status'  => 404,
                'errors'  => ['course_id' => 'Course not found'],
                'message' => 'The specified course does not exist.',
            ];
        }

        // Check if the course is already in the wishlist
        $existing = R::findOne('wishlist', 'user_id = ? AND course_id = ?', [$currentUser->user->id, $data['course_id']]);
        if ($existing) {
            return [
                'error'   => true,
                'status'  => 409,
                'errors'  => ['course' => 'Already in wishlist'],
                'message' => 'This course is already in your wishlist.',
            ];
        }

        // Add to wishlist
        $wishlist          = R::dispense('wishlist');
        $wishlist->user_id = $currentUser->user->id;
        $wishlist->course_id = $data['course_id'];
        $wishlist->added_at = date('Y-m-d H:i:s');

        try {
            R::store($wishlist);
            return [
                'success' => true,
                'message' => 'Course added to wishlist successfully.',
            ];
        } catch (Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'errors'  => ['database' => 'Failed to add to wishlist'],
                'message' => 'An error occurred while adding to wishlist.',
            ];
        }
    }
}
