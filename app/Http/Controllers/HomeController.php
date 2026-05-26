<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Zeus\Post;

class HomeController extends Controller
{
    public function index()
    {
        $announcements = Announcement::activeBanners();

        $blogPosts = collect();
        try {
            $blogPosts = Post::query()
                ->where('post_type', 'post')
                ->where('status', 'publish')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderByDesc('published_at')
                ->take(3)
                ->get();
        } catch (\Throwable $e) {
            $blogPosts = collect();
        }

        return view('frontend.home', compact('announcements', 'blogPosts'));
    }
}
