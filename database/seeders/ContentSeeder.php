<?php

namespace Database\Seeders;

use App\Models\HomePage;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\StoreLocation;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedHomePage();
        $this->seedSiteSettings();
        $this->seedProductEditorial();
    }

    private function seedHomePage(): void
    {
        $page = HomePage::current();
        $page->update([
            'hero_eyebrow' => 'SYSTEM RELEASE // SERIES 2025 // EDITION 04',
            'hero_title' => 'GEOMETRIC UTILITY COLLECTION',
            'hero_subtitle' => 'Form stripped to structural essence. Heavyweight 480GSM Japanese canvas, welded sealed seams, and modular clip attachments engineered for extreme metropolitan transit.',
            'editorial_title' => '"A CHAIR, A BUILDING, A JACKET: EACH IS AN EXERCISE IN PURE LOAD AND TENSION."',
            'editorial_body' => 'Founded on the belief that garments are portable architectures, KRAFT rejects decorative ornament. Every seam serves kinetic necessity; every pocket dimensions itself around concrete tools of contemporary city inhabitants.',
        ]);

        $page->lookbookItems()->updateOrCreate(
            ['title' => 'KRAFT OVERSIZED M-65 ARCHIVAL PARKA'],
            ['caption' => 'SAND OCHRE • DUAL FIDLOCK ATTACHMENT', 'link' => '/shop', 'sort_order' => 1],
        );
    }

    private function seedSiteSettings(): void
    {
        SiteSetting::current()->update([
            'announcement' => 'FW25 WORLDWIDE DISPATCH COMMENCED // COMPLIMENTARY SHIPPING OVER €300 // TOKYO • BERLIN • NEW YORK EXHIBITIONS OPEN',
            'footer_tagline' => 'STRICTLY FUNCTIONAL RELEASES, ARCHIVAL DROPS, AND FORM-FOLLOWS-FUNCTION DESIGN ESSAYS. ZERO SPAM.',
        ]);

        $links = [
            ['INSTAGRAM', 'photo_camera', 'https://instagram.com'],
            ['TIKTOK', 'play_circle', 'https://tiktok.com'],
            ['PINTEREST', 'push_pin', 'https://pinterest.com'],
            ['YOUTUBE LABS', 'smart_display', 'https://youtube.com'],
        ];
        foreach ($links as $i => [$label, $icon, $href]) {
            SocialLink::updateOrCreate(['label' => $label], ['icon' => $icon, 'href' => $href, 'sort_order' => $i + 1, 'is_active' => true]);
        }

        $stores = [
            ['BERLIN MITTE', 'TORSTRASSE 140', 'OPEN 10-19'],
            ['TOKYO AOYAMA', 'MINAMI-AOYAMA 5-CHOME', 'OPEN 11-20'],
            ['NEW YORK SOHO', 'MERCER ST', 'OPEN 11-19'],
            ['LONDON SHOREDITCH', 'REDCHURCH ST', 'OPEN 10-18'],
        ];
        foreach ($stores as $i => [$name, $address, $hours]) {
            StoreLocation::updateOrCreate(['name' => $name], ['address' => $address, 'hours' => $hours, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }

    private function seedProductEditorial(): void
    {
        $copy = [
            'SKU-KFT-9402' => [
                'Double-faced wool, cut to a strict 90-degree shoulder.',
                'Milled in Biella from virgin double-faced wool, the overshirt holds its boxy volume without structural fusing. Oxidized alloy buttons and a clean dropped hem keep the silhouette architectural, layered over knitwear or worn alone as a light jacket.',
                'Dry clean only. Store on a wide hanger and steam gently to refresh; never tumble dry.',
            ],
            'SKU-KFT-TR-301' => [
                'Pleated volume with a pressed, architectural line.',
                'A wide, high-rise pant with deep forward pleats that fall in a single vertical plane. The heavy weave resists creasing and keeps its shape through a full day of transit.',
                'Machine wash cold inside out on a gentle cycle. Hang dry and press on the reverse side.',
            ],
            'SKU-KFT-JKT-11' => [
                'Raw-edge denim in a cropped, boxy blouson.',
                'Rigid selvedge denim left unfinished at the hem and cuffs so the garment frays and fades into your own wear pattern. Internal utility pocket sized for a transit card and phone.',
                'Wash rarely and cold, inside out. Air dry away from direct sunlight to preserve the indigo.',
            ],
            'SKU-KFT-TR-04' => [
                'A modular parka built for extreme metropolitan transit.',
                'Welded sealed seams, a storm-flap closure and clip-on attachment points let the parka adapt from commute to expedition. Heavyweight canvas shell with a structured hood.',
                'Spot clean with a damp cloth. Do not use fabric softener, which degrades the water-resistant finish.',
            ],
            'SKU-KFT-KN-05' => [
                'Dense waffle knit with serious weight.',
                'A rigid, heavyweight waffle structure that traps warmth without bulk. Ribbed collar and cuffs recover their shape after repeated wear.',
                'Hand wash cold or dry clean. Dry flat and reshape while damp.',
            ],
            'SKU-KFT-CHR-09' => [
                'The workwear chore jacket, rebuilt in modular form.',
                'Four oversized patch pockets sized around contemporary city tools, set on a boxy frame in sturdy cotton twill. Designed to soften and mould to the wearer over time.',
                'Machine wash cold with similar colours. Tumble dry low or hang dry.',
            ],
        ];

        foreach ($copy as $sku => [$headline, $long, $care]) {
            Product::where('sku', $sku)->update([
                'headline' => $headline,
                'long_description' => $long,
                'care_instructions' => $care,
                'seo_title' => Product::where('sku', $sku)->value('name').' | KRAFT',
                'seo_description' => $headline,
            ]);
        }
    }
}
