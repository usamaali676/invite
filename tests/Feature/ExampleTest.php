<?php

test('the root address sends visitors to the admin dashboard', function () {
    $this->get('/')->assertRedirect('/admin');
});

test('the app keeps its sub-folder prefix when it is served from one', function () {
    $this->withServerVariables([
        'SCRIPT_NAME' => '/gmbinvite/index.php',
        'PHP_SELF' => '/gmbinvite/index.php',
        'SCRIPT_FILENAME' => public_path('index.php'),
    ])->get('/gmbinvite/index.php')->assertRedirectContains('/gmbinvite/');
});
