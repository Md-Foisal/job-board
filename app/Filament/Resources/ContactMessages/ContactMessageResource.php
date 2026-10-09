<?php

namespace App\Filament\Resources\ContactMessages;

use App\Enums\ContactTopic;
use App\Filament\Resources\ContactMessages\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use App\Notifications\ContactReply;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification as Notifier;
use UnitEnum;

/**
 * The contact form's inbox. Staff answer from here, and the answer goes
 * out from the site's own address; a message that needs no answer (spam,
 * or something already handled elsewhere) can be closed without one.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Messages';

    protected static ?string $pluralModelLabel = 'Messages';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'support/messages';

    public static function getNavigationBadge(): ?string
    {
        $open = ContactMessage::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isActiveStaff() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('From')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('topic')->formatStateUsing(fn (ContactTopic $state) => $state->label()),
                        TextEntry::make('account')
                            ->state(fn (ContactMessage $record) => $record->user_id === null ? 'Not signed in' : 'Signed in as '.$record->user?->email),
                        TextEntry::make('created_at')->label('Received')->dateTime(),
                        TextEntry::make('status')->state(fn (ContactMessage $record) => $record->status())->badge(),
                    ]),
                Section::make('Message')
                    ->schema([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
                Section::make('Our reply')
                    ->visible(fn (ContactMessage $record) => ! $record->isOpen())
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reply_body')
                            ->hiddenLabel()
                            ->placeholder('Closed without a reply')
                            ->extraAttributes(['class' => 'whitespace-pre-line'])
                            ->columnSpanFull(),
                        TextEntry::make('closed_at')
                            ->label('Closed')
                            ->dateTime(),
                        TextEntry::make('closedBy.name')
                            ->label('By')
                            ->placeholder('A former staff member'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->description(fn (ContactMessage $record) => $record->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('topic')
                    ->formatStateUsing(fn (ContactTopic $state) => $state->label())
                    ->badge()
                    ->color(fn (ContactTopic $state) => in_array($state, [ContactTopic::Report, ContactTopic::Privacy], true) ? 'warning' : 'gray'),
                TextColumn::make('body')
                    ->label('Message')
                    ->limit(70)
                    ->wrap()
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('topic')
                    ->options(collect(ContactTopic::cases())->mapWithKeys(fn (ContactTopic $topic) => [$topic->value => $topic->label()])),
            ])
            ->emptyStateHeading('No messages')
            ->emptyStateDescription('Messages sent through the contact page land here.')
            ->recordActions([
                ViewAction::make()->label('Read'),
                static::replyAction(),
                static::closeAction(),
            ]);
    }

    public static function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Reply')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (ContactMessage $record) => $record->isOpen())
            ->modalHeading(fn (ContactMessage $record) => 'Reply to '.$record->name)
            ->modalDescription(fn (ContactMessage $record) => 'Sent to '.$record->email.' from the site’s own address, with their message quoted under it. The message is then closed.')
            ->schema([
                Textarea::make('reply')
                    ->label('Your reply')
                    ->required()
                    ->maxLength(5000)
                    ->rows(8),
            ])
            ->modalSubmitActionLabel('Send reply')
            ->action(function (ContactMessage $record, array $data) {
                $record->forceFill([
                    'reply_body' => $data['reply'],
                    'closed_at' => now(),
                    'closed_by_id' => auth()->id(),
                ])->save();

                Notifier::route('mail', [$record->email => $record->name])->notify(new ContactReply($record));

                Notification::make()->title('Reply sent')->success()->send();
            });
    }

    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label('Close without replying')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->visible(fn (ContactMessage $record) => $record->isOpen())
            ->requiresConfirmation()
            ->modalHeading('Close without replying?')
            ->modalDescription('For spam, or for something already answered another way. Nothing is sent.')
            ->action(function (ContactMessage $record) {
                $record->forceFill([
                    'closed_at' => now(),
                    'closed_by_id' => auth()->id(),
                ])->save();

                Notification::make()->title('Message closed')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContactMessages::route('/'),
        ];
    }
}
