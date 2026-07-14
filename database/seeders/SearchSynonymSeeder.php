<?php

namespace Database\Seeders;

use App\Models\SearchSynonym;
use Illuminate\Database\Seeder;

class SearchSynonymSeeder extends Seeder
{
    /**
     * Seeds the search-term synonym groups that used to live in
     * config/search_synonyms.php, plus one group per marketplace category
     * (see CategorySeeder::categoryNames()) covering the common alternate
     * words people search with (trade names, informal terms, related
     * professions) that aren't close enough in spelling for the fuzzy/plural
     * matching in PublicSearchQueryParser to catch on its own.
     *
     * Each group is a flat list of interchangeable terms; every term in a
     * group is stored with the rest of the group as its synonyms, so a
     * search for any one term also matches the others. Admins can add/edit
     * further entries at runtime via the admin "Search Synonyms" screen.
     */
    public function run(): void
    {
        $groups = [
            // Generic business-role words (apply across all categories).
            ['vendor', 'maker', 'seller', 'dealer', 'supplier', 'retailer', 'provider'],

            // Domain groups carried over from the original static config.
            ['clean', 'cleaner', 'cleaning', 'cleaning services'],
            ['plumb', 'plumber', 'plumbing'],
            ['electric', 'electrician', 'electrical'],
            ['fumigat', 'fumigation', 'fumigation services'],
            ['repair', 'repairs', 'fix', 'fixing'],
            ['spa', 'salon', 'beauty'],

            // One group per marketplace category (CategorySeeder::categoryNames()).
            ['plumber', 'plumbing', 'pipe fitter', 'pipe repair'],
            ['electrician', 'electrical', 'wiring', 'wireman', 'electric repair'],
            ['cleaner', 'cleaning', 'cleaning service', 'housekeeping', 'maid'],
            ['painter', 'painting', 'paint service', 'house painter'],
            ['carpenter', 'carpentry', 'woodwork', 'furniture maker'],
            ['tiler', 'tiling', 'tile installer', 'tile fitter'],
            ['ac technician', 'air conditioner repair', 'air conditioning', 'hvac', 'ac repair'],
            ['handyman', 'handymen', 'odd jobs', 'repair service'],
            ['barber', 'barbershop', 'haircut', 'hair cut'],
            ['hair stylist', 'hairdresser', 'hair salon', 'hairstylist'],
            ['makeup artist', 'makeup', 'mua', 'cosmetics', 'bridal makeup'],
            ['nail technician', 'nail tech', 'manicure', 'pedicure', 'nails'],
            ['spa', 'massage', 'massage therapist', 'wellness spa'],
            ['skincare', 'skincare specialist', 'esthetician', 'facial'],
            ['dispatch rider', 'delivery rider', 'bike dispatch', 'logistics rider'],
            ['mover', 'movers', 'moving service', 'relocation', 'house mover'],
            ['errand', 'errand runner', 'errand service', 'personal assistant'],
            ['courier', 'courier service', 'delivery service', 'logistics'],
            ['caterer', 'catering', 'catering service', 'food vendor'],
            ['baker', 'bakery', 'baking', 'cake maker'],
            ['small chops', 'small chops vendor', 'finger food', 'party snacks'],
            ['private chef', 'personal chef', 'home chef', 'cook'],
            ['event planner', 'event planning', 'wedding planner', 'party planner'],
            ['photographer', 'photography', 'photo shoot', 'camera man', 'cameraman'],
            ['videographer', 'videography', 'video shoot', 'cinematographer'],
            ['mc', 'emcee', 'master of ceremony', 'event host', 'host'],
            ['decorator', 'decoration', 'event decor', 'party decoration'],
            ['dj', 'disc jockey', 'deejay', 'music dj'],
            ['tutor', 'tutoring', 'private lessons', 'teacher'],
            ['language teacher', 'language tutor', 'language lessons', 'language instructor'],
            ['skill trainer', 'skill acquisition', 'vocational trainer', 'training'],
            ['music instructor', 'music teacher', 'music lessons', 'instrument teacher'],
            ['lawyer', 'attorney', 'legal service', 'solicitor', 'barrister'],
            ['accountant', 'accounting', 'bookkeeper', 'bookkeeping', 'tax service'],
            ['consultant', 'consulting', 'advisory', 'consultancy'],
            ['real estate agent', 'realtor', 'property agent', 'real estate'],
            ['insurance agent', 'insurance broker', 'insurance service'],
            ['tailor', 'tailoring', 'sewing', 'seamstress'],
            ['fashion designer', 'fashion design', 'designer clothes', 'couture'],
            ['dry cleaner', 'dry cleaning', 'laundry'],
            ['shoe maker', 'shoemaker', 'cobbler', 'shoe repair'],
            ['nanny', 'babysitter', 'childcare', 'child minder'],
            ['home tutor', 'home tutoring', 'private tutor'],
            ['party rental', 'event rental', 'canopy rental', 'chair rental'],
            ['fitness trainer', 'personal trainer', 'gym instructor', 'fitness coach'],
            ['dietician', 'dietitian', 'nutritionist', 'diet consultant'],
            ['therapist', 'therapy', 'counselor', 'counselling', 'counseling'],
            ['office cleaner', 'office cleaning', 'commercial cleaning', 'janitorial'],
            ['facility manager', 'facility management', 'property management'],
            ['security service', 'security guard', 'security company', 'guard'],
        ];

        /** @var array<string, list<string>> $termSynonyms */
        $termSynonyms = [];

        foreach ($groups as $group) {
            $group = array_values(array_unique(array_map(
                static fn (string $term): string => mb_strtolower(trim($term)),
                $group,
            )));

            foreach ($group as $term) {
                $others = array_values(array_diff($group, [$term]));

                $termSynonyms[$term] = array_values(array_unique(array_merge(
                    $termSynonyms[$term] ?? [],
                    $others,
                )));
            }
        }

        foreach ($termSynonyms as $term => $synonyms) {
            SearchSynonym::query()->updateOrCreate(
                ['term' => $term],
                ['synonyms' => $synonyms],
            );
        }
    }
}
