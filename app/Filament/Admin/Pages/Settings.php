<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Permission;
use App\Enums\TaxRate;
use App\Filament\Schemas\PricingFields;
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
use Filament\Schemas\Components\Utilities\Get;
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
        $this->form->fill($this->readableCharges($this->settings()->attributesToArray()));
    }

    public function save(): void
    {
        $settings = $this->settings();

        $settings->fill($this->storableCharges($this->form->getState()))->save();

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
                    ->description('When this restaurant is open.')
                    ->schema([
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

                Section::make('Tax')
                    ->description('The GST every price on the menu is read against. A dish or an addition may name a rate of its own; anything that does not uses the one set here.')
                    ->schema([
                        TextInput::make('gstin')
                            ->label('GSTIN')
                            ->maxLength(15)
                            ->helperText('The 15-character registration number printed on every tax invoice. Leave empty if this restaurant is not registered.'),

                        Select::make('tax_rate_basis_points')
                            ->label('Default GST rate')
                            ->options(TaxRate::options())
                            ->required()
                            ->native(false)
                            ->helperText('Standalone restaurant service is 5%. Packaged goods sold alongside carry their own rate, set on the item.'),

                        Toggle::make('prices_include_tax')
                            ->label('Menu prices already include GST')
                            ->inline(false)
                            ->helperText('On: ₹100 on the menu is what a guest pays, with the tax already inside it. Off: GST is added to ₹100 at the bill.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Charges')
                    ->description('Added to an order on top of what was ordered. Each one is off until it is switched on, so a restaurant that levies neither says so rather than setting it to nothing.')
                    ->schema([
                        Toggle::make('service_charge_enabled')
                            ->label('Levy a service charge')
                            ->live()
                            ->inline(false)
                            // A service charge is voluntary under the CCPA's
                            // 2022 guidelines, which is why this is a switch
                            // rather than a rate that happens to be zero.
                            ->helperText('Voluntary in India — a guest may ask for it to be removed.'),

                        TextInput::make('service_charge_percentage')
                            ->label('Service charge')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->required(fn (Get $get): bool => (bool) $get('service_charge_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('service_charge_enabled'))
                            ->helperText('A percentage of what was ordered, before tax.'),

                        Toggle::make('parcel_charge_enabled')
                            ->label('Charge for packing a takeaway')
                            ->live()
                            ->inline(false),

                        TextInput::make('parcel_charge')
                            ->label('Parcel charge')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99999)
                            ->step(0.01)
                            ->prefix(fn (): string => PricingFields::currency()->symbol())
                            ->required(fn (Get $get): bool => (bool) $get('parcel_charge_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('parcel_charge_enabled'))
                            ->helperText('A flat amount added to an order that is packed to take away.'),
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
     * Turn the stored charges into the values the form edits.
     *
     * Both are stored the way the rest of the application stores their kind —
     * the service charge in basis points like a tax rate, the parcel charge in
     * minor units like every other amount of money — and both are typed here
     * the way a person says them: "10" percent and "20" rupees.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function readableCharges(array $data): array
    {
        $data['service_charge_percentage'] = ((int) ($data['service_charge_basis_points'] ?? 0))
            / (TaxRate::BASIS_POINTS_PER_WHOLE / 100);

        $data['parcel_charge'] = PricingFields::currency()
            ->toMajorUnits((int) ($data['parcel_charge_minor_units'] ?? 0));

        return $data;
    }

    /**
     * Turn the typed charges back into what gets stored.
     *
     * The rounding happens here, once, so nothing downstream ever sees a float.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storableCharges(array $data): array
    {
        $data['service_charge_basis_points'] = (int) round(
            ((float) ($data['service_charge_percentage'] ?? 0)) * (TaxRate::BASIS_POINTS_PER_WHOLE / 100),
        );

        $data['parcel_charge_minor_units'] = PricingFields::currency()
            ->toMinorUnits($data['parcel_charge'] ?? 0);

        unset($data['service_charge_percentage'], $data['parcel_charge']);

        return $data;
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
