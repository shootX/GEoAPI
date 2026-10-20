<?php

declare(strict_types=1);

namespace Carapi\Platform;

interface UsageStore
{
    /** @return array{count:int,limit:int,exceeded:bool,retry_after:int} */
    public function bumpWindow(string $scope, string $subject, int $limit, int $now): array;

    /** @return array{limit:int,used:int,remaining:int,exhausted:bool,reset_at:int}|null */
    public function previewMonth(int $projectId, int $now): ?array;

    /** @return array{ok:bool,limit:int,used:int,remaining:int,reset_at:int} */
    public function commitSuccess(int $projectId, string $route, int $now): array;

    /** @return array{limit:int,used:int,remaining:int,reset_at:string,per_minute:int,days:list<array{day:string,used:int}>,routes:list<array{route:string,used:int}>}|null */
    public function stats(int $projectId, int $now): ?array;
}
