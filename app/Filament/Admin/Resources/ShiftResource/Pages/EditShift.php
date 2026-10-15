<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShiftResource\Pages;

use App\Filament\Admin\Resources\ShiftResource;
use App\Models\Volunteering\Shift;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    public function form(Schema $schema): Schema
    {
        /** @var Shift $record */
        $record = $this->getRecord();

        return ShiftResource::form($schema->extraAttributes([
            'shiftType' => $record->shiftType,
        ]));
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getSubNavigationParameters(): array
    {
        /** @var Shift $record */
        $record = $this->getRecord();

        return [
            'record' => $record->team,
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Shift';
    }
}
