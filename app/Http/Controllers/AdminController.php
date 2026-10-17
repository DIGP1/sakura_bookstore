<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard');
    }

    public function section(string $section): Response
    {
        return Inertia::render('admin/Section', ['section' => $section]);
    }
}
