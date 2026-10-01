<?php

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\Share;
use Illuminate\Database\Eloquent\Collection;

class ShareRepository
{
    public function find(int $id): ?Share
    {
        return Share::find($id);
    }

    public function findOrFail(int $id): Share
    {
        return Share::findOrFail($id);
    }

    /**
     * The Diagram's Shares that are neither revoked nor expired, newest first.
     *
     * @return Collection<int, Share>
     */
    public function activeFor(Diagram $diagram): Collection
    {
        return $diagram->shares()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Share
    {
        return Share::create($data);
    }

    public function revoke(Share $share): Share
    {
        $share->update(['revoked_at' => now()]);

        return $share;
    }
}
