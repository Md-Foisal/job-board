<?php

namespace App\Filament\Resources\JobPostings;

use App\Actions\ApproveJobPosting;
use App\Actions\RejectJobPosting;
use App\Enums\AvailabilityStatus;
use App\Enums\ModerationStatus;
use App\Filament\Resources\JobPostings\Pages\ManageJobPostings;
use App\Models\JobPosting;
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
            ->with(['company', 'postedBy']);
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getEloquentQuery()
            ->where('moderation_status', ModerationStatus::Pending->value)
            ->count();

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
                        TextEntry::make('published_at')->label('Submitted')->since(),
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
            ->defaultSort('published_at', 'asc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (JobPosting $record) => $record->company?->name),
                IconColumn::make('company.verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn (JobPosting $record) => $record->company?->verified_at !== null),
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
                TextColumn::make('published_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            ->emptyStateHeading('Nothing is waiting')
            ->emptyStateDescription('Postings appear here when an employer publishes them.')
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
            ->visible(fn (JobPosting $record) => $record->moderation_status !== ModerationStatus::Approved)
            ->requiresConfirmation()
            ->modalHeading(fn (JobPosting $record) => "Approve \"{$record->title}\"?")
            ->modalDescription('It goes live on the public site straight away.')
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

    public static function getPages(): array
    {
        return [
            'index' => ManageJobPostings::route('/'),
        ];
    }
}
