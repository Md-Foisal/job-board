<?php

namespace App\Filament\Resources\CompanyReviews;

use App\Actions\ModerateCompanyReview;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Enums\ResponseRejectionReason;
use App\Enums\ReviewPart;
use App\Enums\ReviewRejectionReason;
use App\Filament\Resources\CompanyReviews\Pages\ManageCompanyReviews;
use App\Models\CompanyReview;
use App\Support\ReviewEligibility;
use App\Support\ReviewTextFlags;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The moderation queue for company reviews, and for the companies'
 * answers to them.
 *
 * Staff read every review and every answer before it goes public and
 * decide on each here, separately; they never write, edit or delete one. The application behind a review
 * is shown only in this panel, as the proof that the writer went through
 * the process -- never their name, which the decision does not need.
 *
 * With the AI switched on, each text also carries its hint: the grounds
 * it thinks staff should look at. The hint never reorders the queue --
 * nothing is public while it waits, so the oldest is read first -- and
 * an empty hint is never shown as an all-clear in the list.
 */
class CompanyReviewResource extends Resource
{
    protected static ?string $model = CompanyReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Company reviews';

    protected static ?string $pluralModelLabel = 'Company reviews';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'moderation/reviews';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->moderatableBy(auth()->user())
            ->with(['company', 'application.jobPosting'])
            ->withCount(['reports as open_reports_count' => fn (Builder $query) => $query
                ->where('review_status', ReportStatus::Pending->value)]);
    }

    /**
     * Reviews and answers waiting, together: both are decided here.
     */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getEloquentQuery()
            ->where(fn (Builder $query) => $query
                ->where('moderation_status', ModerationStatus::Pending->value)
                ->orWhere('response_status', ModerationStatus::Pending->value))
            ->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // Seeing the queue is a matter of being active staff. Each review --
    // opening it as much as deciding on it -- asks the policy's moderate()
    // ability, so staff never see the proof behind a review of their own
    // employer; the query leaves those rows out to begin with.

    public static function canViewAny(): bool
    {
        return auth()->user()?->isActiveStaff() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can('moderate', $record) ?? false;
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
                        TextEntry::make('proof')
                            ->label('Could review because')
                            ->state(fn (CompanyReview $record) => ReviewEligibility::describe(ReviewEligibility::basisOf($record->application))),
                        TextEntry::make('applied_for')
                            ->label('Applied for')
                            ->state(fn (CompanyReview $record) => "{$record->application->jobPosting->title}, {$record->application->created_at->setTimezone(FilamentTimezone::get())->format('j M Y')}"),
                        TextEntry::make('flags')
                            ->label('Text contains')
                            ->state(fn (CompanyReview $record) => collect(ReviewTextFlags::in($record->title, $record->body))
                                ->map(fn (string $flag) => ReviewTextFlags::label($flag))
                                ->all())
                            ->badge()
                            ->color('warning')
                            ->placeholder('Nothing flagged'),
                        static::aiHintEntry('ai_hint', ReviewPart::Review),
                        TextEntry::make('open_reports')
                            ->label('Open reports')
                            ->state(fn (CompanyReview $record) => $record->reports()
                                ->where('review_status', ReportStatus::Pending->value)
                                ->latest()
                                ->latest('id')
                                ->pluck('reason')
                                ->all())
                            ->bulleted()
                            ->placeholder('None'),
                        TextEntry::make('last_decision')
                            ->label('Last decision')
                            ->state(fn (CompanyReview $record) => static::lastDecision($record, [
                                ModerationAction::ApproveCompanyReview,
                                ModerationAction::RejectCompanyReview,
                                ModerationAction::DismissReports,
                            ]))
                            ->placeholder('Never reviewed')
                            ->columnSpanFull(),
                    ]),
                Section::make('Review')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('company.name')->label('Company'),
                        TextEntry::make('overall_rating')->label('Overall')->suffix(' / 5'),
                        TextEntry::make('communication_rating')->label('Communication')->suffix(' / 5'),
                        TextEntry::make('job_as_described')->label('Job as described')
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('updated_at')->label('Last written')->since(),
                        TextEntry::make('published_at')->label('Published')->dateTime('F Y')->placeholder('Not yet'),
                        TextEntry::make('title')->columnSpanFull(),
                        TextEntry::make('body')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
                // Who on the company's side wrote the answer is left out: the
                // decision is about what it says, and it speaks for the
                // company.
                Section::make('Company response')
                    ->visible(fn (CompanyReview $record) => $record->response_body !== null)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('response_status')
                            ->label('Status')
                            ->formatStateUsing(fn (ModerationStatus $state) => $state->label())
                            ->badge(),
                        TextEntry::make('responded_at')->label('Written')->since(),
                        TextEntry::make('response_flags')
                            ->label('Text contains')
                            ->state(fn (CompanyReview $record) => collect(ReviewTextFlags::in($record->response_body))
                                ->map(fn (string $flag) => ReviewTextFlags::label($flag))
                                ->all())
                            ->badge()
                            ->color('warning')
                            ->placeholder('Nothing flagged'),
                        static::aiHintEntry('response_ai_hint', ReviewPart::Response),
                        TextEntry::make('response_last_decision')
                            ->label('Last decision')
                            ->state(fn (CompanyReview $record) => static::lastDecision($record, [
                                ModerationAction::ApproveReviewResponse,
                                ModerationAction::RejectReviewResponse,
                            ]))
                            ->placeholder('Never reviewed'),
                        TextEntry::make('response_body')
                            ->label('Response')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
            ]);
    }

    /**
     * The AI's hint on one part, for the record view. "Raised nothing" is
     * said with a reminder that it can miss things, and a text it has not
     * read says so, so neither reads as a verdict.
     */
    public static function aiHintEntry(string $name, ReviewPart $part): TextEntry
    {
        return TextEntry::make($name)
            ->label('AI hint')
            ->hint('Where to look. It never decides.')
            ->state(function (CompanyReview $record) use ($part) {
                $screening = $record->screeningOf($part);

                return match (true) {
                    $screening === null => null,
                    $screening->raisedNothing() => ['Raised nothing. It can miss things, so read it all the same.'],
                    default => $screening->lines(),
                };
            })
            ->bulleted()
            ->placeholder('Not read by the AI')
            ->columnSpanFull();
    }

    /**
     * Which part the list is showing: the answers on the Responses tab,
     * the reviews everywhere else.
     */
    public static function partShownOn(mixed $livewire): ReviewPart
    {
        return ($livewire->activeTab ?? null) === 'responses' ? ReviewPart::Response : ReviewPart::Review;
    }

    /**
     * The latest of the given decisions on a review. Review and response
     * decisions share the trail, so each line asks for its own kind.
     *
     * @param  list<ModerationAction>  $actions
     */
    public static function lastDecision(CompanyReview $record, array $actions): ?string
    {
        $event = $record->moderationEvents()
            ->whereIn('action', array_map(fn (ModerationAction $action) => $action->value, $actions))
            ->with('admin')
            ->latest('created_at')
            ->latest('id')
            ->first();

        if ($event === null) {
            return null;
        }

        $by = $event->admin?->name ?? 'a former staff member';
        $line = "{$event->action->label()} by {$by}, {$event->created_at->diffForHumans()}";

        return $event->reason ? "{$line} — \"{$event->reason}\"" : $line;
    }

    public static function table(Table $table): Table
    {
        return $table
            // Oldest first: the review's own writing time, or on the
            // Responses tab the time the answer was written.
            ->defaultSort(fn ($livewire) => static::partShownOn($livewire) === ReviewPart::Response ? 'responded_at' : 'updated_at', 'asc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60)
                    ->wrap()
                    ->description(fn (CompanyReview $record) => $record->company->name),
                TextColumn::make('overall_rating')
                    ->label('Overall')
                    ->suffix(' / 5'),
                TextColumn::make('flags')
                    ->label('Flags')
                    ->state(fn (CompanyReview $record, $livewire) => collect(static::partShownOn($livewire) === ReviewPart::Response
                        ? ReviewTextFlags::in($record->response_body ?? '')
                        : ReviewTextFlags::in($record->title, $record->body))
                        ->map(fn (string $flag) => ucfirst(ReviewTextFlags::label($flag)))
                        ->all())
                    ->badge()
                    ->color('warning')
                    ->placeholder('None'),
                TextColumn::make('ai_hint')
                    ->label('AI hint')
                    ->state(fn (CompanyReview $record, $livewire) => $record->screeningOf(static::partShownOn($livewire))?->labels() ?? [])
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
                TextColumn::make('response_status')
                    ->label('Response')
                    ->badge()
                    ->formatStateUsing(fn (ModerationStatus $state) => $state->label())
                    ->color(fn (ModerationStatus $state) => match ($state) {
                        ModerationStatus::Pending => 'warning',
                        ModerationStatus::Approved => 'success',
                        ModerationStatus::Rejected => 'danger',
                    })
                    ->placeholder('None'),
                TextColumn::make('open_reports_count')
                    ->label('Reports')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('moderation_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ModerationStatus $state) => $state->label())
                    ->color(fn (ModerationStatus $state) => match ($state) {
                        ModerationStatus::Pending => 'warning',
                        ModerationStatus::Approved => 'success',
                        ModerationStatus::Rejected => 'danger',
                    }),
                TextColumn::make('updated_at')
                    ->label('Written')
                    ->state(fn (CompanyReview $record, $livewire) => static::partShownOn($livewire) === ReviewPart::Response
                        ? $record->responded_at
                        : $record->updated_at)
                    ->since()
                    ->sortable(query: fn (Builder $query, string $direction, $livewire) => $query->orderBy(
                        static::partShownOn($livewire) === ReviewPart::Response ? 'responded_at' : 'updated_at',
                        $direction,
                    )),
            ])
            ->emptyStateHeading(fn ($livewire) => match ($livewire->activeTab ?? null) {
                ModerationStatus::Approved->value => 'Nothing approved yet',
                ModerationStatus::Rejected->value => 'Nothing rejected',
                'responses' => 'No responses waiting',
                default => 'Nothing is waiting',
            })
            ->emptyStateDescription(fn ($livewire) => match ($livewire->activeTab ?? null) {
                ModerationStatus::Approved->value, ModerationStatus::Rejected->value => null,
                'responses' => 'A company\'s answer to a review appears here when it is written or changed.',
                default => 'Reviews appear here when an applicant writes or edits one.',
            })
            ->recordActions([
                ViewAction::make(),
                static::approveAction(),
                static::rejectAction(),
                static::approveResponseAction(),
                static::rejectResponseAction(),
            ]);
    }

    /**
     * Also offered on a rejected review, so a wrong rejection can be
     * undone from the Rejected tab. Not on the Responses tab, where the
     * row is about the answer: the review's Reject next to the answer's
     * made two Reject buttons on one row.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->authorize('moderate')
            ->visible(fn (CompanyReview $record, $livewire) => $record->moderation_status !== ModerationStatus::Approved
                && static::partShownOn($livewire) === ReviewPart::Review)
            ->requiresConfirmation()
            ->modalHeading('Publish this review?')
            ->modalDescription(fn (CompanyReview $record) => $record->open_reports_count > 0
                ? "It goes on {$record->company->name}'s page straight away, and the writer is told. "
                    .((int) $record->open_reports_count === 1 ? 'Its open report' : "Its {$record->open_reports_count} open reports")
                    .' will be closed as not upheld. Read them first.'
                : "It goes on {$record->company->name}'s page straight away, and the writer is told.")
            ->action(function (CompanyReview $record) {
                app(ModerateCompanyReview::class)->approve($record, auth()->user());

                Notification::make()->title('Review published')->success()->send();
            });
    }

    /**
     * Also offered on a published review, so one that turns out to break
     * the rules after a report can be taken back down.
     */
    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->authorize('moderate')
            ->visible(fn (CompanyReview $record, $livewire) => $record->moderation_status !== ModerationStatus::Rejected
                && static::partShownOn($livewire) === ReviewPart::Review)
            ->modalHeading('Keep this review off the company page?')
            ->modalDescription('Only for one of the reasons below, whatever the review says about the company — never because it is negative. The writer is told why and can edit it.')
            ->schema(fn () => static::rejectionFields())
            ->action(function (CompanyReview $record, array $data) {
                app(ModerateCompanyReview::class)->reject(
                    $record,
                    auth()->user(),
                    ReviewRejectionReason::from($data['reason']),
                    $data['note'] ?? null,
                );

                Notification::make()->title('Review rejected')->success()->send();
            });
    }

    /**
     * Shown only while an answer waits, and on a rejected one so that a
     * wrong rejection can be undone. The review's own state does not
     * matter: an approved answer to a review that is off the page is not
     * shown either.
     */
    public static function approveResponseAction(): Action
    {
        return Action::make('approveResponse')
            ->label('Approve response')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->authorize('moderate')
            ->visible(fn (CompanyReview $record) => in_array($record->response_status, [ModerationStatus::Pending, ModerationStatus::Rejected], true)
                && $record->response_body !== null)
            ->requiresConfirmation()
            ->modalHeading(fn (CompanyReview $record) => "Publish {$record->company->name}'s answer?")
            ->modalDescription('It appears under the review straight away, and the writer is told.')
            ->action(function (CompanyReview $record) {
                app(ModerateCompanyReview::class)->approveResponse($record, auth()->user());

                Notification::make()->title('Response published')->success()->send();
            });
    }

    /**
     * Also offered on a published answer, so one that turns out to point
     * at the writer can be taken back down.
     */
    public static function rejectResponseAction(): Action
    {
        return Action::make('rejectResponse')
            ->label('Reject response')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->authorize('moderate')
            ->visible(fn (CompanyReview $record) => in_array($record->response_status, [ModerationStatus::Pending, ModerationStatus::Approved], true)
                && $record->response_body !== null)
            ->modalHeading(fn (CompanyReview $record) => "Keep {$record->company->name}'s answer off the page?")
            ->modalDescription('Only for one of the reasons below, never because staff disagree with the answer. The company\'s owners and managers are told why and can write it again.')
            ->schema([
                Select::make('reason')
                    ->label('Reason')
                    ->options(collect(ResponseRejectionReason::cases())
                        ->mapWithKeys(fn (ResponseRejectionReason $reason) => [$reason->value => $reason->label()])
                        ->all())
                    ->helperText('"Points to who wrote the review" includes describing them: their role, their dates, or what happened to their application.')
                    ->required(),
                Textarea::make('note')
                    ->label('Note to the company (optional)')
                    ->helperText('Say what to change. Do not repeat anything that identifies the writer.')
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (CompanyReview $record, array $data) {
                app(ModerateCompanyReview::class)->rejectResponse(
                    $record,
                    auth()->user(),
                    ResponseRejectionReason::from($data['reason']),
                    $data['note'] ?? null,
                );

                Notification::make()->title('Response rejected')->success()->send();
            });
    }

    /**
     * The same closed list of grounds wherever a review is rejected, here
     * or from the reports queue.
     *
     * @return array<int, Select|Textarea>
     */
    public static function rejectionFields(): array
    {
        return [
            Select::make('reason')
                ->label('Reason')
                ->options(collect(ReviewRejectionReason::cases())
                    ->mapWithKeys(fn (ReviewRejectionReason $reason) => [$reason->value => $reason->label()])
                    ->all())
                ->helperText('"Clearly false or misleading" only when our own records contradict it — such as "they never replied" against replies on file — never because the company disputes it.')
                ->required(),
            Textarea::make('note')
                ->label('Note to the writer (optional)')
                ->helperText('Say what to change. Do not quote personal details back.')
                ->maxLength(1000)
                ->rows(3),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanyReviews::route('/'),
        ];
    }
}
