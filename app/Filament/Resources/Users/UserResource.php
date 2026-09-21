<?php

namespace App\Filament\Resources\Users;

use App\Actions\ReinstateUser;
use App\Actions\SuspendUser;
use App\Enums\AccountStatus;
use App\Enums\MembershipStatus;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\ConfirmsPassword;
use App\Models\User;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Everyone on the platform, and the one decision staff can take about a
 * person: suspending them. Super admins only -- it is the heaviest thing
 * the platform does to anyone.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Users & companies';

    protected static ?string $navigationLabel = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['candidateProfile:id,user_id', 'memberships' => fn ($query) => $query
                ->where('status', MembershipStatus::Active)
                ->with('company:id,name')]);
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

    /**
     * What a person is on the platform, derived the same way the app
     * derives it -- nothing here is a stored role except staff.
     *
     * @return array<int, string>
     */
    public static function standing(User $user): array
    {
        return array_values(array_filter([
            $user->staff_role?->label(),
            $user->candidateProfile ? 'Candidate' : null,
            $user->memberships->isNotEmpty() ? 'Employer' : null,
        ]));
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
                        TextEntry::make('email'),
                        TextEntry::make('account_status')
                            ->label('Account')
                            ->badge()
                            ->formatStateUsing(fn (AccountStatus $state) => $state->label())
                            ->color(fn (AccountStatus $state) => $state === AccountStatus::Active ? 'success' : 'danger'),
                        TextEntry::make('standing')
                            ->state(fn (User $record) => static::standing($record))
                            ->badge()
                            ->placeholder('Nothing set up yet'),
                        TextEntry::make('created_at')->label('Joined')->since(),
                        TextEntry::make('companies')
                            ->label('Works at')
                            ->state(fn (User $record) => $record->memberships
                                ->map(fn ($membership) => "{$membership->company->name} ({$membership->role->label()})")
                                ->all())
                            ->bulleted()
                            ->placeholder('No company')
                            ->columnSpanFull(),
                        TextEntry::make('last_decision')
                            ->label('Last decision')
                            ->state(function (User $record) {
                                $event = $record->moderationEvents()->with('admin')->latest('created_at')->first();

                                if ($event === null) {
                                    return null;
                                }

                                $by = $event->admin?->name ?? 'a former staff member';
                                $line = "{$event->action->label()} by {$by}, {$event->created_at->diffForHumans()}";

                                return $event->reason ? "{$line} -- \"{$event->reason}\"" : $line;
                            })
                            ->placeholder('Never')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('email')->searchable()->hidden(),
                TextColumn::make('standing')
                    ->state(fn (User $record) => static::standing($record))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('account_status')
                    ->label('Account')
                    ->badge()
                    ->formatStateUsing(fn (AccountStatus $state) => $state->label())
                    ->color(fn (AccountStatus $state) => $state === AccountStatus::Active ? 'success' : 'danger'),
                TextColumn::make('created_at')->label('Joined')->since()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                static::suspendAction(),
                static::reinstateAction(),
            ]);
    }

    public static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label('Suspend')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('suspend')
            ->visible(fn (User $record) => $record->account_status === AccountStatus::Active)
            ->modalHeading(fn (User $record) => "Suspend {$record->name}?")
            ->modalDescription('They are signed out and cannot sign back in. Nothing they made is removed. This is not the same as them deleting their own account: they cannot undo it themselves.')
            ->schema(fn () => [
                Textarea::make('reason')
                    ->label('Reason')
                    ->helperText('Kept on the record and shown to the next reviewer.')
                    ->required()
                    ->maxLength(2000)
                    ->rows(3),
                ...ConfirmsPassword::fields(),
            ])
            ->before(fn () => ConfirmsPassword::remember())
            ->action(function (User $record, array $data) {
                app(SuspendUser::class)($record, auth()->user(), $data['reason']);

                Notification::make()->title('Account suspended')->success()->send();
            });
    }

    public static function reinstateAction(): Action
    {
        return Action::make('reinstate')
            ->label('Reinstate')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->authorize('reinstate')
            ->visible(fn (User $record) => $record->account_status !== AccountStatus::Active)
            ->modalHeading(fn (User $record) => "Reinstate {$record->name}?")
            ->modalDescription('They can sign in again straight away, with everything as it was.')
            ->schema([
                Textarea::make('note')->label('Note for the record (optional)')->maxLength(2000)->rows(3),
            ])
            ->action(function (User $record, array $data) {
                app(ReinstateUser::class)($record, auth()->user(), $data['note'] ?? null);

                Notification::make()->title('Account reinstated')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
