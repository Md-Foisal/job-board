<?php

namespace App\Filament\Resources\Reports;

use App\Actions\BanCompany;
use App\Actions\DismissReports;
use App\Actions\RejectJobPosting;
use App\Enums\AccountStatus;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Filament\Support\ConfirmsPassword;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Report;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The reports queue: one row per reported thing, not one per report.
 *
 * Five people reporting the same posting is one problem with five pieces
 * of evidence, and it takes one decision -- which is also how the
 * moderation trail records it. Each row is the most recent report on its
 * subject, standing in for the rest; the count and the full list of
 * reasons come along with it.
 */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Reports';

    // The page title repeats the menu entry word for word, in the same
    // sentence case, so the two never read as different places.
    protected static ?string $pluralModelLabel = 'Reports';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'moderation/reports';

    public static function getEloquentQuery(): Builder
    {
        $latestPerSubject = Report::query()
            ->selectRaw('max(id)')
            ->groupBy('reportable_type', 'reportable_id')
            ->groupByRaw("review_status = 'pending'");

        return parent::getEloquentQuery()
            ->whereIn('id', $latestPerSubject)
            ->with('reportable')
            ->addSelect(['open_count' => Report::query()
                ->selectRaw('count(*)')
                ->from('reports as siblings')
                ->whereColumn('siblings.reportable_type', 'reports.reportable_type')
                ->whereColumn('siblings.reportable_id', 'reports.reportable_id')
                ->where('siblings.review_status', ReportStatus::Pending->value)])
            ->addSelect(['open_reporters' => Report::query()
                ->selectRaw('count(distinct reporter_id)')
                ->from('reports as siblings')
                ->whereColumn('siblings.reportable_type', 'reports.reportable_type')
                ->whereColumn('siblings.reportable_id', 'reports.reportable_id')
                ->where('siblings.review_status', ReportStatus::Pending->value)]);
    }

    /**
     * Reported things with at least one open report -- counted as
     * subjects, like the rows, not as individual reports.
     */
    public static function openSubjectCount(): int
    {
        return Report::query()
            ->where('review_status', ReportStatus::Pending->value)
            ->select('reportable_type', 'reportable_id')
            ->distinct()
            ->get()
            ->count();
    }

    public static function getNavigationBadge(): ?string
    {
        $subjects = static::openSubjectCount();

        return $subjects > 0 ? (string) $subjects : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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

    public static function subjectLabel(Report $report): string
    {
        return match (true) {
            $report->reportable instanceof JobPosting => $report->reportable->title,
            $report->reportable instanceof Company => $report->reportable->name,
            default => 'No longer exists',
        };
    }

    public static function subjectKind(Report $report): string
    {
        return match ($report->reportable_type) {
            (new JobPosting)->getMorphClass() => 'Job posting',
            (new Company)->getMorphClass() => 'Company',
            default => 'Unknown',
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
                Section::make('Reported')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subject')
                            ->state(fn (Report $record) => static::subjectLabel($record))
                            ->url(fn (Report $record) => match (true) {
                                $record->reportable instanceof JobPosting => route('jobs.show', $record->reportable),
                                $record->reportable instanceof Company => route('companies.show', $record->reportable),
                                default => null,
                            }, shouldOpenInNewTab: true),
                        TextEntry::make('kind')
                            ->label('Type')
                            ->state(fn (Report $record) => static::subjectKind($record)),
                        TextEntry::make('employer_record')
                            ->label('Employer record')
                            ->state(function (Report $record) {
                                $company = $record->subjectCompany();

                                if ($company === null) {
                                    return null;
                                }

                                $history = $company->postingModerationRecord();
                                $standing = $company->account_status !== AccountStatus::Active ? ' · banned' : '';

                                return "{$company->name}: {$history['approved']} approved · {$history['rejected']} rejected{$standing}";
                            })
                            ->placeholder('Unknown')
                            ->columnSpanFull(),
                    ]),
                Section::make('What people reported')
                    ->schema([
                        TextEntry::make('reports')
                            ->hiddenLabel()
                            ->state(fn (Report $record) => Report::query()
                                ->where('reportable_type', $record->reportable_type)
                                ->where('reportable_id', $record->reportable_id)
                                ->where('review_status', ReportStatus::Pending->value)
                                ->latest()
                                ->get()
                                ->map(fn (Report $report) => "{$report->reason} ({$report->created_at->diffForHumans()})")
                                ->all())
                            ->bulleted()
                            ->placeholder('Nothing open'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('open_count', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->state(fn (Report $record) => static::subjectLabel($record))
                    ->description(fn (Report $record) => static::subjectKind($record)),
                TextColumn::make('open_count')
                    ->label('Open reports')
                    ->badge()
                    ->color(fn (Report $record, int $state) => $record->open_reporters >= Report::HIDE_AFTER_REPORTERS ? 'danger' : ($state > 0 ? 'warning' : 'gray'))
                    ->description(fn (Report $record) => $record->open_reporters >= Report::HIDE_AFTER_REPORTERS ? 'Hidden from the public' : null)
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Latest reason')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Last reported')
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading('No open reports')
            ->emptyStateDescription('Reports from signed-in users about postings and companies land here.')
            ->recordActions([
                ViewAction::make()->label('Read reports'),
                static::rejectPostingAction(),
                static::banCompanyAction(),
                static::dismissAction(),
            ]);
    }

    public static function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss')
            ->icon(Heroicon::OutlinedCheck)
            ->color('gray')
            ->authorize('moderate')
            ->visible(fn (Report $record) => $record->open_count > 0)
            ->modalHeading(fn (Report $record) => 'Dismiss reports about "'.static::subjectLabel($record).'"?')
            ->modalDescription(fn (Report $record) => "All {$record->open_count} open report(s) will be closed as not upheld. Nothing about the subject changes; if it was hidden while the reports waited, it is back in public view.")
            ->schema([
                Textarea::make('note')
                    ->label('Note for the record (optional)')
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(function (Report $record, array $data) {
                app(DismissReports::class)($record, auth()->user(), $data['note'] ?? null);

                Notification::make()->title('Reports dismissed')->success()->send();
            });
    }

    /**
     * Taking a reported posting down from here goes through exactly the
     * same decision as in the posting queue, so it is recorded the same
     * way and closes the reports as actioned.
     */
    public static function rejectPostingAction(): Action
    {
        return Action::make('rejectPosting')
            ->label('Take posting down')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->authorize('moderate')
            ->visible(fn (Report $record) => $record->open_count > 0
                && $record->reportable instanceof JobPosting
                && $record->reportable->moderation_status !== ModerationStatus::Rejected)
            ->modalHeading(fn (Report $record) => 'Take "'.static::subjectLabel($record).'" down?')
            ->modalDescription('It is rejected and kept off the public site, and the reports are closed as upheld. The employer will be told why.')
            ->schema([
                Select::make('template')
                    ->label('Common reason')
                    ->placeholder('Write your own below')
                    ->options(JobPostingResource::rejectionTemplates())
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
            ->action(function (Report $record, array $data) {
                app(RejectJobPosting::class)($record->reportable, auth()->user(), $data['reason']);

                Notification::make()->title('Posting taken down')->success()->send();
            });
    }

    /**
     * The same ban the company verification page offers, with the same
     * password check, reached from a report about the company.
     */
    public static function banCompanyAction(): Action
    {
        return Action::make('banCompany')
            ->label('Ban company')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('moderate')
            ->visible(fn (Report $record) => $record->open_count > 0
                && $record->reportable instanceof Company
                && $record->reportable->account_status === AccountStatus::Active)
            ->modalHeading(fn (Report $record) => 'Ban '.static::subjectLabel($record).'?')
            ->modalDescription('All of its postings leave the public site at once, and these reports are closed as upheld. Nothing is deleted; lifting the ban brings them back.')
            ->schema(fn () => CompanyResource::banFields())
            ->before(fn () => ConfirmsPassword::remember())
            ->action(function (Report $record, array $data) {
                app(BanCompany::class)($record->reportable, auth()->user(), $data['reason']);

                Notification::make()->title('Company banned')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReports::route('/'),
        ];
    }
}
