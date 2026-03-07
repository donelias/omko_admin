# 🇩🇴 República Dominicana - Helpers & Utilities

Guía completa para usar los helpers y utilities creados para República Dominicana.

## 📁 Archivos Creados

| Archivo | Ubicación | Tipo | Propósito |
|---------|-----------|------|----------|
| `ValidDominicanCedula.php` | `app/Rules/` | Validation Rule | Validar cédula dominicana |
| `DominicanRepublicHelper.php` | `app/Helpers/` | Helper Class | Métodos estáticos para utilidades RD |
| `DominicanRepublicHelper.php` | `app/Traits/` | Trait | Usar en modelos y controladores |
| `RDProvincesSeeder.php` | `database/seeders/` | Seeder | Poblar provincias de RD |
| `2026_02_19_000000_add_rd_provinces.php` | `database/migrations/` | Migration | Migración de provincias |

---

## 🔐 Validación de Cédula Dominicana

### Opción 1: Usar en Validation Rules

```php
use App\Rules\ValidDominicanCedula;

$validated = $request->validate([
    'cedula' => ['required', new ValidDominicanCedula()],
]);
```

### Opción 2: Usar Helper directamente

```php
use App\Helpers\DominicanRepublicHelper;

if (DominicanRepublicHelper::validateCedula($request->cedula)) {
    // Guardar cédula formateada
    $user->cedula = DominicanRepublicHelper::formatCedula($request->cedula);
}
```

### Opción 3: Usar Trait en Modelo

```php
use App\Traits\DominicanRepublicHelper;

class User extends Model {
    use DominicanRepublicHelper;
    
    protected $fillable = ['cedula', 'phone'];
    
    public function setCedulaAttribute($value) {
        if ($this->validateCedula($value)) {
            $this->attributes['cedula'] = $this->formatCedula($value);
        }
    }
}
```

---

## 📞 Validación de Teléfono Dominicano

### Formatos válidos:
- `809-XXX-XXXX`
- `(809) XXXXXXX`
- `1809XXXXXXX`
- `+1-809-XXXXXXX`

### Códigos de área RD:
- `809` - Santo Domingo, Santiago, La Romana
- `829` - Santiago Rodríguez
- `849` - San Cristóbal, San Juan

### Usar en validación:

```php
use App\Helpers\DominicanRepublicHelper;

$validated = $request->validate([
    'phone' => [
        'required',
        function ($attribute, $value, $fail) {
            if (!DominicanRepublicHelper::validatePhoneNumber($value)) {
                $fail('El teléfono no es válido.');
            }
        }
    ],
]);

// Formatear después de validar
$phone = DominicanRepublicHelper::formatPhoneNumber($request->phone);
```

---

## 🗺️ Provincias de República Dominicana

### Obtener todas las provincias:

```php
use App\Helpers\DominicanRepublicHelper;

$provinces = DominicanRepublicHelper::getProvinces();

// Resultado:
// [
//     'azua' => 'Azua',
//     'bahoruco' => 'Bahoruco',
//     'barahona' => 'Barahona',
//     ...
// ]
```

### En Blade (vista):

```blade
<select name="province">
    <option value="">Seleccionar provincia</option>
    @foreach(\App\Helpers\DominicanRepublicHelper::getProvinces() as $slug => $name)
        <option value="{{ $slug }}">{{ $name }}</option>
    @endforeach
</select>
```

### En API (controlador):

```php
use App\Helpers\DominicanRepublicHelper;

class PropertyController extends Controller {
    public function getProvinces() {
        return response()->json(
            DominicanRepublicHelper::getProvinces()
        );
    }
}
```

---

## 🕐 Horarios Comerciales

```php
use App\Helpers\DominicanRepublicHelper;

$businessHours = DominicanRepublicHelper::getBusinessHours();

// Resultado:
// [
//     'monday' => ['start' => '09:00', 'end' => '18:00'],
//     'tuesday' => ['start' => '09:00', 'end' => '18:00'],
//     'wednesday' => ['start' => '09:00', 'end' => '18:00'],
//     'thursday' => ['start' => '09:00', 'end' => '18:00'],
//     'friday' => ['start' => '09:00', 'end' => '18:00'],
//     'saturday' => ['start' => '09:00', 'end' => '14:00'],
//     'sunday' => ['start' => null, 'end' => null], // Cerrado
// ]
```

---

## 🎉 Días Festivos de RD

```php
use App\Helpers\DominicanRepublicHelper;

$holidays = DominicanRepublicHelper::getHolidays();

// Resultado:
// [
//     '01-01' => 'Año Nuevo',
//     '01-06' => 'Epifanía',
//     '02-27' => 'Independencia',
//     '04-14' => 'Viernes Santo',
//     '05-01' => 'Día del Trabajo',
//     '08-16' => 'Restauración',
//     '11-19' => 'Acción de Gracias',
//     '12-25' => 'Navidad',
// ]
```

### Verificar si es feriado:

```php
$today = date('m-d');
$holidays = DominicanRepublicHelper::getHolidays();

if (isset($holidays[$today])) {
    echo "Hoy es feriado: " . $holidays[$today];
}
```

---

## 💰 Moneda

La moneda se configuró como **`RD$`** (Pesos Dominicanos) en la base de datos.

### En Blade:

```blade
<span class="price">{{ $property->price }}</span> <!-- RD$ 2,500,000 -->
```

---

## Ejemplos Completos

### Crear usuario con validación RD:

```php
$validated = $request->validate([
    'name' => 'required|string',
    'cedula' => ['required', new ValidDominicanCedula()],
    'phone' => [
        'required',
        function ($attribute, $value, $fail) {
            if (!DominicanRepublicHelper::validatePhoneNumber($value)) {
                $fail('Teléfono no válido');
            }
        }
    ],
    'province' => ['required', 'in:' . implode(',', array_keys(DominicanRepublicHelper::getProvinces()))],
]);

$user = User::create([
    'name' => $validated['name'],
    'cedula' => DominicanRepublicHelper::formatCedula($validated['cedula']),
    'phone' => DominicanRepublicHelper::formatPhoneNumber($validated['phone']),
    'province' => $validated['province'],
]);
```

### Listar propiedades por provincia:

```php
class PropertyController extends Controller {
    public function byProvince($province) {
        $properties = Property::where('province', $province)
            ->where('status', 1)
            ->get();
        
        return view('properties.list', [
            'properties' => $properties,
            'provinceName' => DominicanRepublicHelper::getProvinces()[$province] ?? 'Desconocida',
            'businessHours' => DominicanRepublicHelper::getBusinessHours(),
        ]);
    }
}
```

---

## 📋 Notas Importantes

1. **Cédula**: El formato es `XXX-XXXXXXX-X` con 11 dígitos
2. **Teléfono**: Los códigos de área válidos son 809, 829, 849
3. **Provincias**: Todas las 32 provincias están en la BD
4. **Moneda**: Se configuró como RD$ automáticamente
5. **Timezone**: Sistema está en `America/Santo_Domingo`

---

## 🧪 Pruebas

### Cédula válida (de prueba): `402-1234567-1`
### Teléfono válido: `+1-809-555-0100`
### Provincia: `santo-domingo`

