<?php

namespace App\Filament\Resources\Companies;

use App\Actions\BanCompany;
use App\Actions\RequestCompanyDocuments;
use App\Actions\RevokeCompanyVerification;
use App\Actions\UnbanCompany;
use App\Actions\VerifyCompany;
use App\Enums\AccountStatus;
use App\Enums\DomainCheck;
use App\Enums\ReportStatus;
use App\Filament\Resources\Companies\Pages\ManageCompanies;
use App\Filament\Support\ConfirmsPassword;
use App\Models\Company;
use App\Support\EmailDomain;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Company verification, and the heavier decisions about a company.
 *
 * Staff never edit a company here -- its profile is its own. They decide
 * whether it has shown it is who it says it is, and whether it may stay
 * on the platform at all.
 */
class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Users & companies';

    protected static ?string $navigationLabel = 'Companies';

    // The page title repeats the menu entry word for word, in the same
    // sentence case, so the two never read as different places.
    protected static ?string $pluralModelLabel = 'Companies';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'companies';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // The email-domain column reads these for every row.
            ->with('managingMemberships')
            ->withCount(['memberships', 'jobPostings'])
            ->withCount(['reports as open_reports_count' => fn (Builder $query) => $query
                ->where('review_status', ReportStatus::Pending->value)]);
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = Company::query()
            ->whereNull('verified_at')
            ->where('account_status', AccountStatus::Active->value)
            ->count();

        return $waiting > 0 ? (string) $waiting : null;
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
                Section::make('Evidence')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('domain_check')
                            ->label('Email and website')
                            ->state(fn (Company $record) => $record->domainCheck()->label())
                            ->badge()
                            ->color(fn (Company $record) => $record->domainCheck()->color()),
                        TextEntry::make('website_url')
                            ->label('Website')
                            ->url(fn (Company $record) => $record->website_url, shouldOpenInNewTab: true)
                            ->placeholder('None'),
                        TextEntry::make('manager_domains')
                            ->label('Owners and managers sign in from')
                            ->state(fn (Company $record) => $record->managerEmailDomains()
                                ->map(fn (string $domain) => EmailDomain::isPersonal($domain) ? "{$domain} (personal)" : $domain)
                                ->all())
                            ->bulleted()
                            ->placeholder('Nobody'),
                        TextEntry::make('identity_type')
                            ->label('Registered as')
                            ->formatStateUsing(fn ($state) => $state?->label()),
                        TextEntry::make('employer_record')
                            ->label('Posting record')
                            ->state(function (Company $record) {
                                $history = $record->postingModerationRecord();

                                return "{$history['approved']} approved · {$history['rejected']} rejected";
                            }),
                        TextEntry::make('open_reports')
                            ->label('Open reports about the company')
                            ->state(fn (Company $record) => $record->reports()
                                ->where('review_status', ReportStatus::Pending->value)
                                ->latest()
                                ->latest('id')
                                ->pluck('reason')
                                ->all())
                            ->bulleted()
                            ->placeholder('None')
                            ->columnSpanFull(),
                        TextEntry::make('last_decision')
                            ->label('Last decision')
                            ->state(function (Company $record) {
                                $event = $record->moderationEvents()->with('admin')->latest('created_at')->latest('id')->first();

                                if ($event === null) {
                                    return null;
                                }

                                $by = $event->admin?->name ?? 'a former staff member';
                                $line = "{$event->action->label()} by {$by}, {$event->created_at->diffForHumans()}";

                                return $event->reason ? "{$line} -- \"{$event->reason}\"" : $line;
                            })
                            ->placeholder('Never reviewed')
                            ->columnSpanFull(),
                    ]),
                Section::make('About')
                    ->schema([
                        TextEntry::make('description')->hiddenLabel()->html()->prose()->placeholder('Nothing written'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Company $record) => $record->identity_type?->label()),
                TextColumn::make('website_host')
                    ->label('Website')
                    ->state(fn (Company $record) => EmailDomain::host($record->website_url))
                    ->placeholder('None'),
                TextColumn::make('domain_check')
                    ->label('Email check')
                    ->badge()
                    ->state(fn (Company $record) => $record->domainCheck())
                    ->formatStateUsing(fn (DomainCheck $state) => $state->label())
                    ->color(fn (DomainCheck $state) => $state->color()),
                TextColumn::make('job_postings_count')->label('Postings'),
                TextColumn::make('open_reports_count')
                    ->label('Reports')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('created_at')->label('Joined')->since()->sortable(),
            ])
            ->emptyStateHeading('Nothing here')
            ->recordActions([
                ViewAction::make()->label('Review'),
                static::verifyAction(),
                static::requestDocumentsAction(),
                static::revokeVerificationAction(),
                static::banAction(),
                static::unbanAction(),
            ]);
    }

    public static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize('moderate')
            ->visible(fn (Company $record) => $record->verified_at === null
                && $record->account_status === AccountStatus::Active)
            ->requiresConfirmation()
            ->modalDescription(fn (Company $record) => $record->domainCheck() === DomainCheck::Match
                ? 'Candidates will see a verified badge on its profile and postings.'
                : "Its email check reads \"{$record->domainCheck()->label()}\". Verify only if you have other proof, such as documents.")
            ->action(function (Company $record) {
                app(VerifyCompany::class)($record, auth()->user());

                Notification::make()->title('Company verified')->success()->send();
            });
    }

    public static function requestDocumentsAction(): Action
    {
        return Action::make('requestDocuments')
            ->label('Ask for documents')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('warning')
            ->authorize('moderate')
            ->visible(fn (Company $record) => $record->verified_at === null
                && $record->account_status === AccountStatus::Active)
            ->schema([
                Textarea::make('reason')
                    ->label('What should they send?')
                    ->helperText('For example a trade licence, or an email from an address on the company website.')
                    ->required()
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(function (Company $record, array $data) {
                app(RequestCompanyDocuments::class)($record, auth()->user(), $data['reason']);

                Notification::make()->title('Request recorded')->success()->send();
            });
    }

    public static function revokeVerificationAction(): Action
    {
        return Action::make('revokeVerification')
            ->label('Revoke verification')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('warning')
            ->authorize('moderate')
            ->visible(fn (Company $record) => $record->verified_at !== null)
            ->schema([
                Textarea::make('reason')->label('Reason')->required()->maxLength(2000)->rows(3),
            ])
            ->action(function (Company $record, array $data) {
                app(RevokeCompanyVerification::class)($record, auth()->user(), $data['reason']);

                Notification::make()->title('Verification revoked')->success()->send();
            });
    }

    /**
     * The heaviest decision about a company, so it re-asks for the staff
     * member's password.
     */
    public static function banAction(): Action
    {
        return Action::make('ban')
            ->label('Ban')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('moderate')
            ->visible(fn (Company $record) => $record->account_status === AccountStatus::Active)
            ->modalHeading(fn (Company $record) => "Ban {$record->name}?")
            ->modalDescription(fn (Company $record) => "All {$record->job_postings_count} of its postings leave the public site at once. Nothing is deleted; lifting the ban brings them back.")
            ->schema(fn () => static::banFields())
            ->before(fn () => ConfirmsPassword::remember())
            ->action(function (Company $record, array $data) {
                app(BanCompany::class)($record, auth()->user(), $data['reason']);

                Notification::make()->title('Company banned')->success()->send();
            });
    }

    /**
     * Shared with the reports queue, which can ban a reported company.
     *
     * @return array<int, Component|Field>
     */
    public static function banFields(): array
    {
        return [
            Textarea::make('reason')
                ->label('Reason')
                ->helperText('Kept on the record and shown to the next reviewer.')
                ->required()
                ->maxLength(2000)
                ->rows(3),
            ...ConfirmsPassword::fields(),
        ];
    }

    public static function unbanAction(): Action
    {
        return Action::make('unban')
            ->label('Lift ban')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->authorize('moderate')
            ->visible(fn (Company $record) => $record->account_status !== AccountStatus::Active)
            ->requiresConfirmation()
            ->modalDescription('Its postings return to the public site as they were.')
            ->action(function (Company $record) {
                app(UnbanCompany::class)($record, auth()->user());

                Notification::make()->title('Ban lifted')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanies::route('/'),
        ];
    }
}
