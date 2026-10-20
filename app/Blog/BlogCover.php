<?php

declare(strict_types=1);

namespace App\Blog;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class BlogCover
{
    public function store(BlogPost $post, Request $request): BlogPost
    {
        $data = $request->validate([
            'file' => ['required_without:content_base64', 'file', 'image', 'max:4096'],
            'content_base64' => ['required_without:file', 'string'],
            'filename' => ['required_with:content_base64', 'string', 'max:200'],
            'cover_alt' => ['nullable', 'string', 'max:200'],
        ]);
        if ($request->hasFile('file')) {
            $upload = $request->file('file');
            $binary = (string) file_get_contents($upload->getRealPath());
            $name = $upload->getClientOriginalName();
        } else {
            $binary = base64_decode((string) $data['content_base64'], true);
            if ($binary === false) {
                throw ValidationException::withMessages([
                    'content_base64' => 'content_base64 არ არის სწორი Base64.',
                ]);
            }
            $name = (string) $data['filename'];
        }
        if (strlen($binary) > 4 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'სურათი 4 მბ-ზე დიდია.',
            ]);
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'cover';
        $path = 'blog/covers/'.bin2hex(random_bytes(16)).'-'.$safe;
        Storage::disk('public')->put($path, $binary);
        $this->deleteFile($post->cover_path);
        $post->cover_path = $path;
        if (array_key_exists('cover_alt', $data)) {
            $post->cover_alt = $data['cover_alt'];
        }
        $post->save();

        return $post;
    }

    public function delete(BlogPost $post): void
    {
        $this->deleteFile($post->cover_path);
        $post->cover_path = null;
        $post->save();
    }

    public function deleteFile(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
