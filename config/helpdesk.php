<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Helpdesk
    |--------------------------------------------------------------------------
    |
    | Support tickets that workspaces open from the dashboard.
    |
    | `default_assignee_id` is the users.id that new tickets are assigned to.
    | Leave it empty to assign them to the first admin user instead, or to
    | nobody when there is no admin.
    |
    */

    'default_assignee_id' => filter_var(
        env('HELPDESK_DEFAULT_ASSIGNEE_ID'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1], 'flags' => FILTER_NULL_ON_FAILURE],
    ),

];
