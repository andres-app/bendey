<?php
class NumeroALetras
{
    private $UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis',
        'siete', 'ocho', 'nueve', 'diez', 'once', 'doce',
        'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete',
        'dieciocho', 'diecinueve', 'veinte'
    ];

    private $DECENAS = [
        '', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta',
        'sesenta', 'setenta', 'ochenta', 'noventa'
    ];

    private $CENTENAS = [
        '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos',
        'quinientos', 'seiscientos', 'setecientos', 'ochocientos',
        'novecientos'
    ];

    public function toWords($number)
    {
        $number = number_format($number, 2, '.', '');
        list($entero, $decimal) = explode('.', $number);

        $texto = $this->convertir($entero);
        $texto = strtoupper(trim($texto)) . " CON " . str_pad($decimal, 2, '0', STR_PAD_RIGHT) . "/100";

        return $texto;
    }

    private function convertir($number)
    {
        if ($number == 0) {
            return 'cero';
        } elseif ($number < 21) {
            return $this->UNIDADES[$number];
        } elseif ($number < 100) {
            $decena = intval($number / 10);
            $unidad = $number % 10;
            return $this->DECENAS[$decena] . ($unidad ? ' y ' . $this->UNIDADES[$unidad] : '');
        } elseif ($number < 1000) {
            $centena = intval($number / 100);
            $resto = $number % 100;
            if ($number == 100) return 'cien';
            return $this->CENTENAS[$centena] . ($resto ? ' ' . $this->convertir($resto) : '');
        } elseif ($number < 1000000) {
            $miles = intval($number / 1000);
            $resto = $number % 1000;
            if ($miles == 1) {
                return 'mil' . ($resto ? ' ' . $this->convertir($resto) : '');
            } else {
                return $this->convertir($miles) . ' mil' . ($resto ? ' ' . $this->convertir($resto) : '');
            }
        } elseif ($number < 1000000000000) {
            $millones = intval($number / 1000000);
            $resto = $number % 1000000;
            if ($millones == 1) {
                return 'un millón' . ($resto ? ' ' . $this->convertir($resto) : '');
            } else {
                return $this->convertir($millones) . ' millones' . ($resto ? ' ' . $this->convertir($resto) : '');
            }
        } else {
            return 'Número demasiado grande';
        }
    }
}
