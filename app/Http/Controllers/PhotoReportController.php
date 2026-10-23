<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AdminAccess;
use App\Models\User;
use Carapi\Catalog\Connection;
use Carapi\Catalog\UnavailableException;
use Electrik\Notifications\DatabaseNotification;
use Electrik\Support\Operators;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PDOException;

final class PhotoReportController
{
    public function __invoke(string $id): JsonResponse
    {
        if (preg_match('/^[1-9][0-9]{0,18}$/', $id) !== 1) {
            return $this->error(404, 'not_found', 'ფოტო ვერ მოიძებნა.');
        }

        try {
            $name = $this->photoName($id);
        } catch (UnavailableException | PDOException) {
            return $this->error(503, 'unavailable', 'კატალოგი დროებით მიუწვდომელია.');
        }

        if ($name === null) {
            return $this->error(404, 'not_found', 'ფოტო ვერ მოიძებნა.');
        }

        if ($this->alreadyReported($id)) {
            return response()->json(['ok' => true]);
        }

        $admins = $this->admins();
        $operators = Operators::emails();
        if ($operators !== []) {
            $admins = $admins->merge(
                User::query()->whereIn('email', $operators)->get()
            )->unique('id');
        }

        if ($admins->isEmpty()) {
            return $this->error(503, 'unavailable', 'შეტყობინება ვერ გაიგზავნა.');
        }

        $label = trim($name['brand'].' · '.$name['model'], ' ·');
        foreach ($admins as $admin) {
            DatabaseNotification::send($admin, 'არასწორი ფოტო', $label.' — მოდელი #'.$id, url('/account/errors'));
        }

        return response()->json(['ok' => true]);
    }

    private function alreadyReported(string $id): bool
    {
        return DB::table('notifications')
            ->where('created_at', '>=', now()->subDay())
            ->where('data', 'like', '%#'.$id.'%')
            ->exists();
    }

    private function admins(): Collection
    {
        $user = new User();
        $ids = AdminAccess::adminRoleHolders()->pluck('model_has_roles.model_id');

        return User::query()->whereIn($user->getKeyName(), $ids)->get();
    }

    /** @return array{brand: string, model: string}|null */
    private function photoName(string $id): ?array
    {
        $stmt = Connection::pdo()->prepare(<<<'SQL'
            SELECT b.name AS brand, m.name AS model
            FROM public.models m
            JOIN public.brands b ON b.id = m.brand_id
            WHERE m.id = :id
              AND EXISTS (
                SELECT 1
                FROM public.model_photos p
                WHERE p.model_id = m.id
                  AND p.verified = true
                  AND p.image IS NOT NULL
              )
        SQL);
        $stmt->bindValue('id', $id);
        $stmt->execute();
        $row = $stmt->fetch();
        if (! is_array($row)) {
            return null;
        }

        return [
            'brand' => trim((string) ($row['brand'] ?? '')),
            'model' => trim((string) ($row['model'] ?? '')),
        ];
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
