<?php

declare(strict_types=1);

namespace App\Actions\Terrains;

use App\DTO\Terrains\CreateTerrainData;
use App\Models\Terrain;
use Illuminate\Support\Str;

class CreateTerrainAction
{
    public function execute(CreateTerrainData $data): Terrain
    {
        return Terrain::query()->create([
            'name' => $data->name,
            'description' => $data->description,
            'code' => $this->generateUniqueCode($data->name),
            'is_active' => true,
        ]);
    }

    protected function generateUniqueCode(string $name): string
    {
        $baseCode = Str::slug($name);
        $baseCode = $baseCode !== '' ? $baseCode : 'terrain';
        $candidate = $baseCode;
        $suffix = 2;

        while (Terrain::query()->where('code', $candidate)->exists()) {
            $candidate = sprintf('%s-%d', $baseCode, $suffix);
            $suffix++;
        }

        return $candidate;
    }
}
