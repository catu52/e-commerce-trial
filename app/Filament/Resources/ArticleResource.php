<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Services\ArticlePreviewUrl;
use App\Support\PublicImageUrl;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use UnitEnum;

class ArticleResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Article::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Editorial articles';

    protected static ?string $modelLabel = 'article';

    protected static ?string $viewPermission = 'content.manage';

    protected static ?string $managePermission = 'content.manage';

    public static function categorySuggestions(): array
    {
        return ['Essay', 'Materials', 'Care Guide', 'Lookbook'];
    }

    public static function deleteCover(?string $path): void
    {
        if ($path) {
            Storage::disk(PublicImageUrl::DISK)->delete($path);
        }
    }

    public static function isScheduled(Article $record): bool
    {
        return $record->status === Article::STATUS_PUBLISHED
            && $record->published_at !== null
            && $record->published_at->isFuture();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Article')->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Warning: changing the slug changes the public URL (/editorial/{slug}); old links will stop working.'),
                Forms\Components\Textarea::make('excerpt')
                    ->required()
                    ->maxLength(300)
                    ->rows(3),
                Forms\Components\MarkdownEditor::make('body')
                    ->required()
                    ->helperText('Markdown only; raw HTML is not rendered.')
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make('Cover & meta')->schema([
                Forms\Components\FileUpload::make('cover_image_path')
                    ->label('Cover image')
                    ->image()
                    ->imageEditor()
                    ->disk(PublicImageUrl::DISK)
                    ->directory('content/articles')
                    ->visibility('public')
                    ->maxSize(4096),
                Forms\Components\TextInput::make('cover_image_alt')
                    ->label('Cover alt text')
                    ->maxLength(255),
                Forms\Components\TextInput::make('author_name')
                    ->placeholder('KRAFT Studio')
                    ->maxLength(255),
                Forms\Components\TextInput::make('category')
                    ->maxLength(100)
                    ->datalist(static::categorySuggestions()),
                Forms\Components\Toggle::make('is_featured')->label('Featured'),
            ])->columns(2),
            Section::make('Publishing')->schema([
                Forms\Components\Select::make('status')
                    ->options([Article::STATUS_DRAFT => 'Draft', Article::STATUS_PUBLISHED => 'Published'])
                    ->default(Article::STATUS_DRAFT)
                    ->required()
                    ->native(false),
                Forms\Components\DateTimePicker::make('published_at')
                    ->helperText('Leave empty to publish now. A future date schedules the article.'),
            ])->columns(2),
            Section::make('SEO')->schema([
                Forms\Components\TextInput::make('seo_title')
                    ->maxLength(70)
                    ->helperText('Up to 70 characters. Defaults to the title.'),
                Forms\Components\Textarea::make('seo_description')
                    ->maxLength(160)
                    ->rows(2)
                    ->helperText('Up to 160 characters. Defaults to the excerpt.'),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image_path')->label('Cover')->disk(PublicImageUrl::DISK)->square(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(60),
                Tables\Columns\TextColumn::make('category')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->state(fn (Article $record) => static::isScheduled($record) ? 'Scheduled' : ucfirst($record->status))
                    ->color(fn (string $state) => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_featured')->label('Featured')->boolean(),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([Article::STATUS_DRAFT => 'Draft', Article::STATUS_PUBLISHED => 'Published']),
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn () => Article::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category')->all()),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Featured'),
            ])
            ->recordActions([
                Actions\Action::make('viewOnSite')
                    ->label(fn (Article $record) => $record->isLive() ? 'View on site' : 'Preview on site')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Article $record) => app(ArticlePreviewUrl::class)->for($record), shouldOpenInNewTab: true),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->after(fn (Article $record) => static::deleteCover($record->cover_image_path)),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
