<?php

namespace App\Filament\Resources\Categories;

use App\Actions\MergeCategory;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
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
 * The curated category list. It stays curated -- one entry per real
 * category, however people spell it -- which is what keeps browsing and job alerts reliable.
 *
 * Something in use is never simply removed: it is merged into the entry
 * it duplicates, which carries its postings across first.
 * Only an entry nothing uses can be removed outright, and a removed one
 * can be restored.
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->withCount(['jobPostings']);
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
        return $record->job_postings_count;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->rules([
                        fn (?Category $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                            $clash = Category::withTrashed()
                                ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                ->first();

                            if ($clash === null) {
                                return;
                            }

                            $fail($clash->trashed()
                                ? "A removed category is already called \"{$clash->name}\". Restore it from the Removed tab instead."
                                : "There is already a category called \"{$clash->name}\".");
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
            ->visible(fn (Category $record) => static::canViewAny() && ! $record->trashed())
            ->modalHeading(fn (Category $record) => "Merge \"{$record->name}\" into another category")
            ->modalDescription(fn (Category $record) => "Its {$record->job_postings_count} posting(s) are refiled under the one you choose. Then this one is removed. Its old address stops working.")
            ->schema([
                Select::make('into')
                    ->label('Merge into')
                    ->options(fn (Category $record) => Category::query()
                        ->whereKeyNot($record->getKey())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Category $record, array $data) {
                $into = Category::findOrFail($data['into']);

                app(MergeCategory::class)($record, $into);

                Notification::make()->title("Merged into {$into->name}")->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
