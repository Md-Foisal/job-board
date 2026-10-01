<?php

namespace App\Filament\Resources\CompanyReviews;

use App\Actions\ModerateCompanyReview;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The moderation queue for company reviews.
 *
 * Staff read every review before it goes public and decide on it here;
 * they never write, edit or delete one. The application behind a review
 * is shown only in this panel, as the proof that the writer went through
 * the process -- never their name, which the decision does not need.
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

    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getEloquentQuery()->where('moderation_status', ModerationStatus::Pending->value)->count();

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
                            ->state(fn (CompanyReview $record) => "{$record->application->jobPosting->title}, {$record->application->created_at->format('j M Y')}"),
                        TextEntry::make('flags')
                            ->label('Text contains')
                            ->state(fn (CompanyReview $record) => collect(ReviewTextFlags::in($record->title, $record->body))
                                ->map(fn (string $flag) => ReviewTextFlags::label($flag))
                                ->all())
                            ->badge()
                            ->color('warning')
                            ->placeholder('Nothing flagged'),
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
                            ->state(function (CompanyReview $record) {
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
                Section::make('Review')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('company.name')->label('Company'),
                        TextEntry::make('overall_rating')->label('Overall')->suffix(' / 5'),
                        TextEntry::make('communication_rating')->label('Communication')->suffix(' / 5'),
                        TextEntry::make('job_as_described')->label('Job as described')
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('updated_at')->label('Last written')->since(),
                        TextEntry::make('published_at')->label('Published')->date('F Y')->placeholder('Not yet'),
                        TextEntry::make('title')->columnSpanFull(),
                        TextEntry::make('body')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'asc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(60)
                    ->description(fn (CompanyReview $record) => $record->company->name),
                TextColumn::make('overall_rating')
                    ->label('Overall')
                    ->suffix(' / 5'),
                TextColumn::make('flags')
                    ->label('Flags')
                    ->state(fn (CompanyReview $record) => collect(ReviewTextFlags::in($record->title, $record->body))
                        ->map(fn (string $flag) => ucfirst(ReviewTextFlags::label($flag)))
                        ->all())
                    ->badge()
                    ->color('warning')
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
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading(fn ($livewire) => match ($livewire->activeTab ?? null) {
                ModerationStatus::Approved->value => 'Nothing approved yet',
                ModerationStatus::Rejected->value => 'Nothing rejected',
                default => 'Nothing is waiting',
            })
            ->emptyStateDescription(fn ($livewire) => match ($livewire->activeTab ?? null) {
                ModerationStatus::Approved->value, ModerationStatus::Rejected->value => null,
                default => 'Reviews appear here when an applicant writes or edits one.',
            })
            ->recordActions([
                ViewAction::make(),
                static::approveAction(),
                static::rejectAction(),
            ]);
    }

    /**
     * Also offered on a rejected review, so a wrong rejection can be
     * undone from the Rejected tab.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->authorize('moderate')
            ->visible(fn (CompanyReview $record) => $record->moderation_status !== ModerationStatus::Approved)
            ->requiresConfirmation()
            ->modalHeading('Publish this review?')
            ->modalDescription(fn (CompanyReview $record) => $record->open_reports_count > 0
                ? "It goes on {$record->company->name}'s page straight away, and its {$record->open_reports_count} open report(s) will be closed as not upheld. Read them first."
                : "It goes on {$record->company->name}'s page straight away. The writer and the company are told.")
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
            ->visible(fn (CompanyReview $record) => $record->moderation_status !== ModerationStatus::Rejected)
            ->modalHeading('Keep this review off the company page?')
            ->modalDescription('Only for one of the reasons below, whatever the review says about the company -- never because it is negative. The writer is told why and can edit it.')
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
                ->helperText('"Clearly false or misleading" only when our own records contradict it -- such as "they never replied" against replies on file -- never because the company disputes it.')
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
