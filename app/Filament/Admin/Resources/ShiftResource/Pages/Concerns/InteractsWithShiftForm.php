<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShiftResource\Pages\Concerns;

use App\Filament\Admin\Resources\ShiftResource;
use App\Models\Volunteering\Shift;
use Filament\Schemas\Schema;

/**
 * Supplies shift-type context when building {@see ShiftResource::form()} on record pages.
 */
trait InteractsWithShiftForm
{
    public function form(Schema $schema): Schema
    {
        /** @var Shift $record */
        $record = $this->getRecord();

        return ShiftResource::form($schema->extraAttributes([
            'shiftType' => $record->shiftType,
        ]));
    }
}
