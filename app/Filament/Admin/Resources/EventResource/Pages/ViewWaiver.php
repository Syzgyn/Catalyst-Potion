<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\EventResource\Pages;

use App\Filament\Admin\Resources\EventResource;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class ViewWaiver extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Waiver';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    public bool $hasWaiver = false;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->url(EditWaiver::getUrl(['record' => $this->record]))
                ->label(fn () => $this->hasWaiver ? 'Edit Waiver' : 'Create Waiver')
                ->hidden(function () {
                    /** @var Event $event */
                    $event = $this->record;

                    return $event->completedWaivers()->exists();
                }),
        ];
    }

    public function getBreadcrumb(): string
    {
        return 'Waiver';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('A waiver cannot be modified once it has been signed.')
                    ->color('info')
                    ->visible(fn (): bool => $this->getRecord()->completedWaivers()->exists()),
                $this->getFormContentComponent()
                    ->visible(fn (): bool => $this->hasWaiver),
                Text::make('There is currently no waiver for this event.')
                    ->visible(fn (): bool => ! $this->hasWaiver),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\RichEditor::make('content')
                        ->required()
                        ->columnSpanFull(),
                ])
                    ->columns(1)
                    ->relationship('waiver'),
            ]);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Event $model */
        $model = $this->getRecord();

        $this->hasWaiver = $model->waiver !== null;
    }
}
