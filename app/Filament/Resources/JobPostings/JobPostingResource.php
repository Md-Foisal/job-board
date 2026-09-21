<?php

namespace App\Filament\Resources\JobPostings;

use App\Actions\ApproveJobPosting;
use App\Actions\RejectJobPosting;
use App\Enums\AccountStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\JobPostings\Pages\ManageJobPostings;
use App\Models\JobPosting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The moderation queue for job postings.
 *
 * Staff judge postings here; they never write them. There is no create,
 * edit or delete -- the only things that can happen to a posting in this
 * panel are the two decisions, each of which leaves a record behind.
 */
class JobPostingResource extends Resource
{
    protected static ?string $model = JobPosting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Job postings';

    // The page title repeats the menu entry word for word, in the same
    // sentence case, so the two never read as different places.
    protected static ?string $pluralModelLabel = 'Job postings';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    // Matches the admin route map in the UI architecture, rather than the
    // model-derived default (/admin/job-postings).
    protected static ?string $slug = 'moderation/jobs';

    /**
     * Drafts are the employer's own business until they choose to
     * publish -- nothing has been submitted, so there is nothing to judge.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('availability_status', '!=', AvailabilityStatus::Draft->value)
            ->with(['company', 'postedBy'])
            ->withCount(['reports as open_reports_count' => fn (Builder $query) => $query
                ->where('review_status', ReportStatus::Pending->value)]);
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getEloquentQuery()->awaitingReview()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // The model's own policy answers questions about employers editing
    // their postings, with a company in hand; none of that applies here.
    // Seeing the queue is a matter of being active staff, and the two
    // decisions below ask the policy's moderate() ability per record.

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
                Section::make('Before you decide')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('employer_record')
                            ->label('Employer record')
                            ->state(function (JobPosting $record) {
                                $history = $record->company->postingModerationRecord();

                                if ($history['approved'] === 0 && $history['rejected'] === 0) {
                                    return 'New employer -- nothing approved yet';
                                }

                                return "{$history['approved']} approved · {$history['rejected']} rejected";
                            }),
                        TextEntry::make('company_standing')
                            ->label('Company')
                            ->state(fn (JobPosting $record) => match (true) {
                                $record->company->account_status !== AccountStatus::Active => 'Banned',
                                $record->company->verified_at !== null => 'Verified',
                                default => 'Not verified',
                            })
                            ->badge()
                            ->color(fn (string $state) => match ($state) {
                                'Banned' => 'danger',
                                'Verified' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('open_reports')
                            ->label('Open reports')
                            ->state(fn (JobPosting $record) => $record->reports()
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
                            ->state(function (JobPosting $record) {
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
                Section::make('Posting')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->columnSpanFull(),
                        TextEntry::make('company.name')->label('Company'),
                        TextEntry::make('postedBy.name')->label('Posted by')->placeholder('Account removed'),
                        TextEntry::make('employment_type')->label('Type')
                            ->formatStateUsing(fn ($state) => $state?->label()),
                        TextEntry::make('workplace_type')->label('Workplace')
                            ->formatStateUsing(fn ($state) => $state?->label()),
                        TextEntry::make('location_country')->label('Location')
                            ->formatStateUsing(fn ($state, JobPosting $record) => collect([$record->location_city, $state])->filter()->join(', '))
                            ->placeholder('Not given'),
                        TextEntry::make('submitted_at')->label('Submitted')->since(),
                    ]),
                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')->hiddenLabel()->html()->prose(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'asc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (JobPosting $record) => $record->company?->name),
                IconColumn::make('company.verified_at')
                    ->label('Verified')
                    ->boolean()
                    // Most companies are not verified, and that is not a
                    // fault: a red cross on every row reads as an alarm and
                    // hides the rows that really need a closer look.
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->falseColor('gray')
                    ->getStateUsing(fn (JobPosting $record) => $record->company?->verified_at !== null),
                TextColumn::make('open_reports_count')
                    ->label('Reports')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('postedBy.name')
                    ->label('Posted by')
                    ->placeholder('Account removed'),
                TextColumn::make('moderation_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ModerationStatus $state) => $state->label())
                    ->color(fn (ModerationStatus $state) => match ($state) {
                        ModerationStatus::Pending => 'warning',
                        ModerationStatus::Approved => 'success',
                        ModerationStatus::Rejected => 'danger',
                    }),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            // One table behind four tabs: "Nothing is waiting" on an empty
            // Approved tab would say the opposite of what it shows.
            ->emptyStateHeading(fn ($livewire) => match ($livewire->activeTab ?? null) {
                ModerationStatus::Approved->value => 'Nothing approved yet',
                ModerationStatus::Rejected->value => 'Nothing rejected',
                'not_open' => 'No closed or lapsed posting is waiting',
                default => 'Nothing is waiting',
            })
            ->emptyStateDescription(fn ($livewire) => match ($livewire->activeTab ?? null) {
                ModerationStatus::Approved->value, ModerationStatus::Rejected->value => null,
                'not_open' => 'A pending posting lands here if its company closes it or it runs out while waiting, and goes back to Waiting when reopened or extended.',
                default => 'Postings appear here when an employer publishes them.',
            })
            ->recordActions([
                ViewAction::make(),
                static::approveAction(),
                static::rejectAction(),
            ]);
    }

    /**
     * Also offered on a rejected posting, so a wrong rejection can be
     * undone from the Rejected tab.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->authorize('moderate')
            ->visible(fn (JobPosting $record) => $record->moderation_status !== ModerationStatus::Approved
                && $record->company->account_status === AccountStatus::Active)
            ->requiresConfirmation()
            ->modalHeading(fn (JobPosting $record) => "Approve \"{$record->title}\"?")
            ->modalDescription(fn (JobPosting $record) => $record->open_reports_count > 0
                ? "It goes live on the public site straight away, and its {$record->open_reports_count} open report(s) will be closed as not upheld. Read them first."
                : 'It goes live on the public site straight away.')
            ->action(function (JobPosting $record) {
                app(ApproveJobPosting::class)($record, auth()->user());

                Notification::make()->title('Posting approved')->success()->send();
            });
    }

    /**
     * Also offered on an approved posting, so a posting that turns out to
     * be a problem after going live can be taken back down.
     */
    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->authorize('moderate')
            ->visible(fn (JobPosting $record) => $record->moderation_status !== ModerationStatus::Rejected)
            ->modalHeading(fn (JobPosting $record) => "Reject \"{$record->title}\"?")
            ->modalDescription('It will be kept off the public site. The employer will be told why.')
            ->schema([
                Select::make('template')
                    ->label('Common reason')
                    ->placeholder('Write your own below')
                    ->options(static::rejectionTemplates())
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $state ? $set('reason', $state) : null)
                    ->dehydrated(false),
                Textarea::make('reason')
                    ->label('Reason')
                    ->helperText('Written for the employer: say what has to change for it to be approved.')
                    ->required()
                    ->maxLength(2000)
                    ->rows(4),
            ])
            ->action(function (JobPosting $record, array $data) {
                app(RejectJobPosting::class)($record, auth()->user(), $data['reason']);

                Notification::make()->title('Posting rejected')->success()->send();
            });
    }

    /**
     * Starting text for the violations job boards most often reject for
     * (Indeed's documented list). Picking one fills the reason, which the
     * reviewer can still edit -- the reason stays free text, so no
     * template ever has to fit a case it was not written for.
     *
     * @return array<string, string>
     */
    public static function rejectionTemplates(): array
    {
        return collect([
            'Misleading title' => 'The job title does not describe the role in the posting. Use the actual title of the position.',
            'Asks for money' => 'The posting asks applicants for a fee or for financial details. Jobs here must never charge applicants.',
            'Apply elsewhere' => 'The posting sends applicants to apply outside this site. Applications have to come through the Apply button.',
            'Discriminatory' => 'The posting sets a requirement that excludes people for who they are rather than what the job needs. Remove it.',
            'Pay or location' => 'The pay or the location is missing or does not match the description. State them plainly.',
            'Duplicate' => 'This repeats a posting you already have live. Edit the existing one instead of posting it again.',
        ])->mapWithKeys(fn (string $text, string $label) => [$text => $label])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJobPostings::route('/'),
        ];
    }
}
