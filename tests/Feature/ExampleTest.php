<?php

test('the root address sends visitors to the admin dashboard', function () {
    $this->get('/')->assertRedirect('/admin');
});
