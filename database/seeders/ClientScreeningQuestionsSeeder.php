<?php

namespace Database\Seeders;

use App\Models\ClientScreeningQuestion;
use Illuminate\Database\Seeder;

/**
 * Crea el cuestionario de depuración de clientes con ponderaciones y
 * puntajes por opción. Los puntos son 0-100 por respuesta y el score final
 * es el promedio ponderado según el `weight` de cada pregunta.
 */
class ClientScreeningQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'question_key' => 'personas_cantidad',
                'question_text' => '¿Para cuántas personas es el alquiler?',
                'field_type' => 'number',
                'options' => null,
                'placeholder' => 'Ej: 3',
                'weight' => 5,
                'rank' => 1,
            ],
            [
                'question_key' => 'adultos_cantidad',
                'question_text' => '¿Cantidad de adultos?',
                'field_type' => 'number',
                'options' => null,
                'placeholder' => 'Ej: 2',
                'weight' => 5,
                'rank' => 2,
            ],
            [
                'question_key' => 'adultos_laboran',
                'question_text' => '¿Laboran los adultos?',
                'field_type' => 'radio',
                'options' => [
                    ['label' => 'Sí, todos', 'value' => 'si', 'points' => 100],
                    ['label' => 'Algunos', 'value' => 'algunos', 'points' => 50],
                    ['label' => 'No', 'value' => 'no', 'points' => 10],
                ],
                'placeholder' => null,
                'weight' => 15,
                'rank' => 3,
            ],
            [
                'question_key' => 'ninos_cantidad',
                'question_text' => '¿Cantidad de niños?',
                'field_type' => 'number',
                'options' => null,
                'placeholder' => 'Ej: 0',
                'weight' => 3,
                'rank' => 4,
            ],
            [
                'question_key' => 'ninos_edades',
                'question_text' => '¿Edades de los niños?',
                'field_type' => 'text',
                'options' => null,
                'placeholder' => 'Ej: 5 y 8 años',
                'weight' => 3,
                'rank' => 5,
            ],
            [
                'question_key' => 'vehiculo_propio',
                'question_text' => '¿Vehículo propio?',
                'field_type' => 'radio',
                'options' => [
                    ['label' => 'Sí', 'value' => 'si', 'points' => 100],
                    ['label' => 'No', 'value' => 'no', 'points' => 60],
                ],
                'placeholder' => null,
                'weight' => 5,
                'rank' => 6,
            ],
            [
                'question_key' => 'lugar_trabajo',
                'question_text' => '¿Lugar de trabajo?',
                'field_type' => 'text',
                'options' => null,
                'placeholder' => 'Ej: Zona Oriental, Santo Domingo',
                'weight' => 3,
                'rank' => 7,
            ],
            [
                'question_key' => 'tiene_mascota',
                'question_text' => '¿Tiene mascota?',
                'field_type' => 'radio',
                'options' => [
                    ['label' => 'Sí', 'value' => 'si', 'points' => 30],
                    ['label' => 'No', 'value' => 'no', 'points' => 100],
                ],
                'placeholder' => null,
                'weight' => 5,
                'rank' => 8,
            ],
            [
                'question_key' => 'garante_solidario',
                'question_text' => '¿Tiene posible garante solidario?',
                'field_type' => 'radio',
                'options' => [
                    ['label' => 'Sí', 'value' => 'si', 'points' => 100],
                    ['label' => 'Es posible', 'value' => 'posible', 'points' => 60],
                    ['label' => 'No', 'value' => 'no', 'points' => 15],
                ],
                'placeholder' => null,
                'weight' => 12,
                'rank' => 9,
            ],
            [
                'question_key' => 'ingresos_mensuales',
                'question_text' => '¿Ingresos aproximados mensuales?',
                'field_type' => 'select',
                'options' => [
                    ['label' => 'Menos de RD$ 20,000', 'value' => 'menos_20000', 'points' => 0],
                    ['label' => 'RD$ 20,000 - RD$ 39,999', 'value' => '20000_39999', 'points' => 25],
                    ['label' => 'RD$ 40,000 - RD$ 59,999', 'value' => '40000_59999', 'points' => 50],
                    ['label' => 'RD$ 60,000 - RD$ 99,999', 'value' => '60000_99999', 'points' => 75],
                    ['label' => 'RD$ 100,000 o más', 'value' => '100000_mas', 'points' => 100],
                ],
                'placeholder' => null,
                'weight' => 18,
                'rank' => 10,
            ],
            [
                'question_key' => 'situacion_empleo',
                'question_text' => '¿Situación laboral?',
                'field_type' => 'select',
                'options' => [
                    ['label' => 'Empleado formal', 'value' => 'empleado_formal', 'points' => 100],
                    ['label' => 'Independiente / negocio propio', 'value' => 'independiente', 'points' => 60],
                    ['label' => 'Pensionado', 'value' => 'pensionado', 'points' => 70],
                    ['label' => 'Estudiante', 'value' => 'estudiante', 'points' => 30],
                    ['label' => 'Desempleado', 'value' => 'desempleado', 'points' => 10],
                ],
                'placeholder' => null,
                'weight' => 12,
                'rank' => 11,
            ],
            [
                'question_key' => 'antiguedad_empleo',
                'question_text' => '¿Cuánto tiempo lleva en su empleo actual?',
                'field_type' => 'select',
                'options' => [
                    ['label' => 'Menos de 1 año', 'value' => 'menos_1', 'points' => 40],
                    ['label' => '1 a 2 años', 'value' => '1_2', 'points' => 60],
                    ['label' => '3 a 5 años', 'value' => '3_5', 'points' => 80],
                    ['label' => 'Más de 5 años', 'value' => 'mas_5', 'points' => 100],
                ],
                'placeholder' => null,
                'weight' => 5,
                'rank' => 12,
            ],
            [
                'question_key' => 'fecha_mudanza',
                'question_text' => '¿Cuándo desea mudarse?',
                'field_type' => 'select',
                'options' => [
                    ['label' => 'De inmediato', 'value' => 'inmediato', 'points' => 100],
                    ['label' => 'En 1 mes', 'value' => '1_mes', 'points' => 80],
                    ['label' => 'En 1 a 3 meses', 'value' => '1_3_meses', 'points' => 60],
                    ['label' => 'Aún sin fecha definida', 'value' => 'sin_fecha', 'points' => 30],
                ],
                'placeholder' => null,
                'weight' => 5,
                'rank' => 13,
            ],
            [
                'question_key' => 'desalojo_previo',
                'question_text' => '¿Ha sido desalojado anteriormente?',
                'field_type' => 'radio',
                'options' => [
                    ['label' => 'No', 'value' => 'no', 'points' => 100],
                    ['label' => 'Sí', 'value' => 'si', 'points' => 0],
                ],
                'placeholder' => null,
                'weight' => 8,
                'rank' => 14,
            ],
            [
                'question_key' => 'problemas_legales',
                'question_text' => '¿Tiene problemas legales o demandas?',
                'field_type' => 'radio',
                'options' => [
                    ['label' => 'No', 'value' => 'no', 'points' => 100],
                    ['label' => 'Sí', 'value' => 'si', 'points' => 0],
                ],
                'placeholder' => null,
                'weight' => 8,
                'rank' => 15,
            ],
            [
                'question_key' => 'fumar_inmueble',
                'question_text' => '¿Fuma dentro del inmueble?',
                'field_type' => 'radio',
                'options' => [
                    ['label' => 'No', 'value' => 'no', 'points' => 100],
                    ['label' => 'Sí', 'value' => 'si', 'points' => 15],
                ],
                'placeholder' => null,
                'weight' => 3,
                'rank' => 16,
            ],
            [
                'question_key' => 'tiempo_permanencia',
                'question_text' => '¿Cuánto tiempo planea permanecer?',
                'field_type' => 'select',
                'options' => [
                    ['label' => 'Menos de 6 meses', 'value' => 'menos_6', 'points' => 50],
                    ['label' => '6 a 12 meses', 'value' => '6_12', 'points' => 75],
                    ['label' => '1 a 2 años', 'value' => '1_2_anios', 'points' => 85],
                    ['label' => 'Más de 2 años', 'value' => 'mas_2', 'points' => 100],
                ],
                'placeholder' => null,
                'weight' => 5,
                'rank' => 17,
            ],
        ];

        foreach ($questions as $question) {
            ClientScreeningQuestion::updateOrCreate(
                ['question_key' => $question['question_key']],
                $question
            );
        }
    }
}