<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Members directory — visible to logged-in users (route is `auth`-gated).
 */
class MembersController extends BaseController
{
    public function index(): void
    {
        $members = $this->database->query(
            "SELECT u.id, u.handle, u.about, u.avatar_path, u.is_admin, u.created_at,
                    (SELECT COUNT(*) FROM listings l
                      WHERE l.user_id = u.id AND l.is_published = true) AS listing_count
               FROM users u
              ORDER BY u.created_at ASC"
        );

        $this->render('members/index', [
            'title'   => 'Members',
            'members' => $members,
        ]);
    }
}
