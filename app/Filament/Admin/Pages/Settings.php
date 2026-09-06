<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Restaurant;
use App\Models\RestaurantSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use LogicException;

/**
 * Configuration for the one restaurant whose panel this is.
 *
 * The restaurant comes from the panel's tenant, which is the subdomain being
 * served, so this page can only ever read or write the settings of the
 * restaurant the visitor is already inside.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected string $view = 'filament.admin.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 90;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->settings()->attributesToArray());
    }

    public function save(): void
    {
        $settings = $this->settings();

        $settings->fill($this->form->getState())->save();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->settings())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contact')
                    ->description('How guests reach this restaurant.')
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('Contact email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Contact phone')
                            ->tel()
                            ->maxLength(32),
                    ])
                    ->columns(2),

                Section::make('Trading')
                    ->description('When this restaurant is open and what it charges in.')
                    ->schema([
                        Select::make('timezone')
                            ->label('Timezone')
                            ->options(array_combine(
                                timezone_identifiers_list(),
                                timezone_identifiers_list(),
                            ))
                            ->searchable()
                            ->required(),
                        Select::make('currency')
                            ->label('Currency')
                            ->options(Currency::options())
                            ->required(),
                        TimePicker::make('opens_at')
                            ->label('Opens at')
                            ->seconds(false),
                        TimePicker::make('closes_at')
                            ->label('Closes at')
                            ->seconds(false),
                        Toggle::make('accepts_orders')
                            ->label('Accepting orders')
                            ->helperText('Turn this off to stop taking new orders without closing the storefront.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())->key('form-actions'),
            ]);
    }

    public function getHeading(): string
    {
        return 'Settings';
    }

    public function getSubheading(): string
    {
        return sprintf('Configuration for %s.', $this->restaurant()->name);
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }

    /**
     * The settings of the restaurant whose panel this is, created on first view.
     */
    private function settings(): RestaurantSetting
    {
        $restaurant = $this->restaurant();

        return $restaurant->settings()->firstOrCreate([]);
    }

    /**
     * The restaurant the panel is currently serving.
     */
    private function restaurant(): Restaurant
    {
        $tenant = Filament::getTenant();

        throw_unless($tenant instanceof Restaurant, LogicException::class, 'The restaurant settings page requires a restaurant tenant.');

        return $tenant;
    }
}
