<?php

namespace App\Filament\Resources\Skills;

use App\Actions\MergeSkill;
use App\Filament\Resources\Skills\Pages\ManageSkills;
use App\Models\Skill;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * The curated skill list. It stays curated -- one entry per real
 * skill, however people spell it -- which is what keeps the match score honest ("React" and "ReactJS" as two entries would never match each other).
 *
 * Something in use is never simply removed: it is merged into the entry
 * it duplicates, which carries its postings and candidates across first.
 * Only an entry nothing uses can be removed outright, and a removed one
 * can be restored.
 */
class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?string $navigationLabel = 'Skills';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'skills';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->withCount(['jobPostings', 'candidateProfiles']);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->isActiveStaff() && $user->isSuperAdmin();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && ! $record->trashed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && ! $record->trashed() && static::usage($record) === 0;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return static::canViewAny() && $record->trashed();
    }

    public static function usage(Model $record): int
    {
        return $record->job_postings_count + $record->candidate_profiles_count;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->rules([
                        fn (?Skill $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                            $clash = Skill::withTrashed()
                                ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                ->first();

                            if ($clash === null) {
                                return;
                            }

                            $fail($clash->trashed()
                                ? "A removed skill is already called \"{$clash->name}\". Restore it from the Removed tab instead."
                                : "There is already a skill called \"{$clash->name}\".");
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('job_postings_count')->label('Postings')->sortable(),
                TextColumn::make('candidate_profiles_count')->label('Candidates')->sortable(),
                TextColumn::make('deleted_at')->label('Removed')->since()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                // Filament does not consult canEdit()/canDelete()/canRestore()
                // for these on a simple resource's table, so each one asks
                // explicitly. Hidden actions cannot be called either.
                EditAction::make()->label('Rename')
                    ->visible(fn (Model $record) => static::canEdit($record)),
                static::mergeAction(),
                DeleteAction::make()->label('Remove')
                    ->visible(fn (Model $record) => static::canDelete($record)),
                RestoreAction::make()
                    ->visible(fn (Model $record) => static::canRestore($record)),
            ]);
    }

    public static function mergeAction(): Action
    {
        return Action::make('merge')
            ->label('Merge into…')
            ->icon(Heroicon::OutlinedArrowsPointingIn)
            ->color('warning')
            ->visible(fn (Skill $record) => static::canViewAny() && ! $record->trashed())
            ->modalHeading(fn (Skill $record) => "Merge \"{$record->name}\" into another skill")
            ->modalDescription(fn (Skill $record) => "Its {$record->job_postings_count} posting(s) and {$record->candidate_profiles_count} candidate(s) move across first; where someone already has both, the stronger claim is kept. Then this one is removed.")
            ->schema([
                Select::make('into')
                    ->label('Merge into')
                    ->options(fn (Skill $record) => Skill::query()
                        ->whereKeyNot($record->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Skill $record, array $data) {
                $into = Skill::findOrFail($data['into']);

                app(MergeSkill::class)($record, $into);

                Notification::make()->title("Merged into {$into->name}")->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSkills::route('/'),
        ];
    }
}
