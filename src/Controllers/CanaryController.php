<?php

declare(strict_types=1);

namespace App\Controllers;

class CanaryController extends BaseController
{
    public function show(): void
    {
        // Get the current date for the canary statement
        $currentDate = date('F j, Y');
        
        // Calculate next update date (typically quarterly - 3 months)
        $nextUpdateDate = date('F j, Y', strtotime('+3 months'));
        
        // Canary statement data
        $canaryData = [
            'current_date' => $currentDate,
            'next_update_date' => $nextUpdateDate,
            'last_updated' => $currentDate,
            'statements' => [
                'No National Security Letters have been received',
                'No gag orders have been served',
                'No warrants from any government entity have been served',
                'No subpoenas from any government entity have been served',
                'No court orders compelling disclosure of user data have been received',
                'No requests to install surveillance software have been received',
                'The integrity of our systems has not been compromised',
                'We have not been forced to modify our systems to facilitate surveillance'
            ]
        ];
        
        $this->render('canary/index', $canaryData);
    }
}