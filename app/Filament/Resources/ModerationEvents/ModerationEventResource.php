<?php

namespace App\Filament\Resources\ModerationEvents;

use App\Enums\ModerationAction;
use App\Filament\Resources\ModerationEvents\Pages\ListModerationEvents;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\ModerationEvent;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use UnitEnum;

/**
 * The moderation trail: who did what, to what, when and why.
 *
 * Read-only for everyone, super admins included -- an audit log that can
 * be edited or pruned is not an audit log. Every member of staff can read
 * it, not only super admins: moderators seeing each other's decisions,
 * and their own, is part of what keeps the decisions consistent.
 */
class ModerationEventResource extends Resource
{
    protected static ?string $model = ModerationEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Moderation log';

    // The page title repeats the menu entry word for word, in the same
    // sentence case, so the two never read as different places.
    protected static ?string $pluralModelLabel = 'Moderation log';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'moderation/log';

    public static function getEloquentQuery(): Builder
    {
        // Deleted and erased people stay on the trail as who they are now
        // ("Person: Deleted user"), not as something that "no longer exists":
        // the row is still there, only emptied.
        return parent::getEloquentQuery()->with([
            'admin',
            'subject' => fn (MorphTo $morphTo) => $morphTo->constrain([
                User::class => fn (Builder $query) => $query->withTrashed(),
            ]),
        ]);
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

    public static function subjectLabel(ModerationEvent $event): string
    {
        return match (true) {
            $event->subject instanceof JobPosting => "Posting: {$event->subject->title}",
            $event->subject instanceof Company => "Company: {$event->subject->name}",
            $event->subject instanceof User => "Person: {$event->subject->name}",
            default => 'No longer exists',
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('action')
                            ->formatStateUsing(fn (ModerationAction $state) => $state->label())
                            ->badge(),
                        TextEntry::make('created_at')->label('When')->dateTime(),
                        TextEntry::make('admin.name')->label('By')->placeholder('A former staff member'),
                        TextEntry::make('subject')
                            ->label('About')
                            ->state(fn (ModerationEvent $record) => static::subjectLabel($record)),
                        TextEntry::make('reason')->placeholder('No reason recorded')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('admin.name')
                    ->label('By')
                    ->placeholder('A former staff member'),
                TextColumn::make('action')
                    ->badge()
                    ->formatStateUsing(fn (ModerationAction $state) => $state->label())
                    ->color(fn (ModerationAction $state) => $state->requiresReason() ? 'danger' : 'gray'),
                TextColumn::make('subject')
                    ->label('About')
                    ->state(fn (ModerationEvent $record) => static::subjectLabel($record)),
                TextColumn::make('reason')
                    ->limit(60)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(collect(ModerationAction::cases())
                        ->mapWithKeys(fn (ModerationAction $action) => [$action->value => $action->label()])
                        ->all()),
                SelectFilter::make('admin_id')
                    ->label('By')
                    ->options(fn () => User::query()->whereNotNull('staff_role')->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('subject_type')
                    ->label('About')
                    ->options([
                        (new JobPosting)->getMorphClass() => 'Postings',
                        (new Company)->getMorphClass() => 'Companies',
                        (new User)->getMorphClass() => 'People',
                    ]),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->emptyStateHeading('No decisions yet')
            ->emptyStateDescription('Every approval, rejection, dismissal, verification, ban and suspension will be recorded here.')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModerationEvents::route('/'),
        ];
    }
}
