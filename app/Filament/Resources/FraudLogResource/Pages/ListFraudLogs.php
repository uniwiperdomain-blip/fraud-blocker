<?php

namespace App\Filament\Resources\FraudLogResource\Pages;

use App\Filament\Resources\FraudLogResource;
use Filament\Resources\Pages\ListRecords;

class ListFraudLogs extends ListRecords
{
    protected static string $resource = FraudLogResource::class;
}
