<?php

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade  


require_once __DIR__ . '/../models/Course.php';

require_once __DIR__ . '/../models/Media.php';

require_once __DIR__ . '/../models/User.php';

require_once __DIR__ . '/../models/CourseSection.php';

require_once __DIR__ . '/../models/CoursePriceTier.php';
class Cart
{
    public static function addCart($data)
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

        // ✅ Check if the course exists and is published
        $course = R::findOne('courses', ' id = ? AND published = ? ', [$data['course_id'], 1]);
        if (!$course) {
            return [
                'error'   => true,
                'status'  => 404,
                'errors'  => ['course_id' => 'Course not found'],
                'message' => 'The specified course does not exist.',
            ];
        }

        // ✅ Check if this course is already in the user's cart
        $existingCart = R::findOne(
            'carts',
            ' user_id = ? AND course_id = ? ',
            [$currentUser->user->id, $data['course_id']]
        );

        if ($existingCart) {
            return [
                'error'   => true,
                'status'  => 409,
                'errors'  => ['course_id' => 'Already in cart'],
                'message' => 'This course is already in your cart.',
            ];
        }

        // ✅ Add new course to cart
        $cart = R::dispense('carts');
        $cart->uuid        = Uuid::uuid4()->toString();
        $cart->user_id     = $currentUser->user->id;
        $cart->course_id   = $data['course_id'];
        $cart->created_at  = R::isoDateTime();
        $cart->updated_at  = R::isoDateTime();

        R::store($cart);

        return [
            'error'   => false,
            'status'  => 201,
            'data'    => $cart,
            'message' => 'Course added to cart successfully.',
        ];
    }


    public static function getCurrentUserCart()
    {
        $currentUser = AuthController::getCurrentUser();

        // ✅ Fetch carts ordered by creation date (newest first)
        $carts = R::find(
            'carts',
            ' user_id = ? ORDER BY created_at DESC ',
            [$currentUser->user->id]
        );
        $total = 0.00;
        $cartItems = [];
        foreach ($carts as $cart) {
            $course = R::findOne('courses', ' id = ? ', [$cart->course_id]);

            if ($course->price_tier) {
                $priceTier = CoursePriceTier::getCoursePriceTierById($course->price_tier);
                if ($priceTier) {
                    $total += (float) $priceTier['price'];
                }
            }

            if ($course) {
                $cartItems[] = [
                    'cart_id'     => $cart->id,
                    'created_at'  => $cart->created_at,
                    'uuid'        => $cart->uuid,
                    'course' => [
                        'uuid'        => $course->uuid,
                        'title'       => $course->title,
                        'slug'        => $course->slug,
                        'cover_image' => Media::getMediaById($course->cover_image),
                        'author' => User::getPublicProfileById($course->author_id),
                        'resources_count'     => CourseSection::getCourseSectionCount((int) $course->id)
                    ],
                ];
            }
        }



        return [
            'error'   => false,
            'status'  => 200,
            'data'    => [
                'cartItems' => $cartItems,
                'cartTotal' => number_format($total, 2),
            ],
            'message' => 'Cart items retrieved successfully.',
        ];
    }


    public static function removeFromCart($id): array
    {
        $currentUser = AuthController::getCurrentUser();
        $cart = R::findOne('carts', ' id = ? AND user_id = ? ', [$id, $currentUser->user->id]);
        if ($currentUser->user->id !== $cart->user_id) {
            return [
                'status'  => 403,
                'message' => 'You are not authorized to perform this action.'
            ];
        }


        if ($cart) {
            R::trash($cart);
            return [
                'status'  => 200,
                'message' => 'Item removed from cart successfully.'
            ];
        }
        return [
            'status'  => 404,
            'message' => 'Cart item not found.'
        ];
    }

    public static function checkCourseInCart($courseId)
    {
        $currentUser = AuthController::getCurrentUser(true); // true → allow null return

        if (!$currentUser) {
            // Not logged in, so not in cart
            return false;
        }

        $cart = R::findOne('carts', ' user_id = ? AND course_id = ? ', [
            $currentUser->user->id,
            $courseId
        ]);

        return $cart ? true : false;
    }

    public static function getCartCount()
    {
        $currentUser = AuthController::getCurrentUser(true); // true → allow null return

        if (!$currentUser) {
            // Not logged in, so cart count is 0
            return [
                'count' => 0
            ];
        }

        $count = R::count('carts', ' user_id = ? ', [$currentUser->user->id]);
        return [
            'count' => (int) $count
        ];
    }
}
