<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;


class ConvertVillasToRoomsSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Property::where('type', 'villa')->orderBy('id')->get();

        if ($rooms->isEmpty()) {
            $this->command->warn('Walang nahanap na property na type=villa. Baka na-run na ito dati, o iba ang setup.');
            return;
        }

        // Iwasan ang pag-duplicate kung na-run na ito dati
        $alreadyConverted = Property::where('property_name', 'like', 'Villa Elena%')->exists();
        if ($alreadyConverted) {
            $this->command->warn('May "Villa Elena" record na. Tumigil para hindi ma-duplicate. I-check muna manually.');
            return;
        }

        $totalPrice    = 0;
        $totalWeekend  = 0;
        $totalCapacity = 0;
        $allAmenities  = [];
        $descriptions  = [];

        foreach ($rooms as $index => $room) {
            $letter = chr(65 + $index); // A, B, C, D, E, F...

            $totalPrice    += (float) $room->base_price;
            $totalWeekend  += (float) ($room->weekend_price ?? $room->base_price);
            $totalCapacity += (int) $room->max_capacity;
            $allAmenities   = array_merge($allAmenities, $room->amenities ?? []);
            $descriptions[] = "Room {$letter}: " . ($room->description ?? '');

            $room->update([
                'property_name' => "Room {$letter}",
                'type'          => 'room',
            ]);
        }

        Property::create([
            'property_name' => 'Villa Elena (Whole Villa)',
            'type'          => 'villa',
            'description'   => 'Ang buong Villa Elena, kasama ang lahat ng ' . $rooms->count() . ' kwarto, exclusive para sa inyong grupo lamang.',
            'max_capacity'  => $totalCapacity,
            'base_price'    => $totalPrice,
            'weekend_price' => $totalWeekend,
            'amenities'     => array_values(array_unique($allAmenities)),
            'status'        => 'available',
            'is_featured'   => true,
            'sort_order'    => 0,
            'created_by'    => null,
        ]);

        $this->command->info("Tapos! {$rooms->count()} room(s) na-convert. Bagong Villa Elena master record: base_price = {$totalPrice}, max_capacity = {$totalCapacity}.");
    }
}