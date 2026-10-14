<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $data) {
            Article::updateOrCreate(['slug' => $data['slug']], $data + ['author_name' => 'KRAFT Studio']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function articles(): array
    {
        $published = Article::STATUS_PUBLISHED;

        return [
            [
                'slug' => 'form-follows-fabric',
                'title' => 'Form Follows Fabric: Why We Build Like the Bauhaus',
                'excerpt' => 'A century on, the Bauhaus rule still holds: honest materials, visible construction, no decoration that does not earn its place.',
                'category' => 'Essay',
                'is_featured' => true,
                'status' => $published,
                'published_at' => now()->subDays(3),
                'seo_title' => 'Form Follows Fabric — KRAFT Studio Journal',
                'seo_description' => 'How Bauhaus principles shape every KRAFT Studio garment.',
                'body' => <<<'MD'
## The workshop, not the runway

In 1919 Walter Gropius asked designers to return to the workshop. Not to the atelier, not to the gallery — to the bench, where a thing is made by hands that understand its material. At KRAFT Studio we keep that question pinned above the cutting table: *does this detail do a job?*

### Three rules we borrow

- **Honest materials.** Heavy cotton looks like heavy cotton. Wool is never disguised as something softer than it is.
- **Visible construction.** Flat-felled seams, bar-tacks and topstitching are left in the open because they show how the garment holds together.
- **Geometry over ornament.** Squares, circles and triangles organise our patterns. Colour is flat, primary and deliberate.

> "The ultimate aim of all creative activity is a building." — Walter Gropius, 1919

We read that line a little differently. For us, the building is the body: a structure you move through all day, which should be strong, legible and calm.

### What neo-brutalism adds

Where the Bauhaus was optimistic and smooth, the brutalist streak in our work is blunt. Hard outlines. Oversized proportions. Hardware you can see and hear. We want clothes that announce their own logic rather than apologise for it.

### The practical result

Every KRAFT piece starts with a fabric decision, not a sketch. We choose the cloth, test how it folds, creases and ages, and only then draw the silhouette it suggests. The sketch comes last, and usually gets simpler.

That is why our archive reads as one family across seasons. The palette changes slightly; the logic never does.

1. Pick the material.
2. Respect its limits.
3. Cut as few pieces as the body allows.
4. Show how it is joined.

If you wear one of our pieces for ten years and it looks better at the end, we did the job. Form followed fabric, and the fabric followed through.
MD,
            ],
            [
                'slug' => 'heavyweight-cotton-explained',
                'title' => 'Heavyweight Cotton, Explained',
                'excerpt' => 'GSM, weave and finish: what the numbers on a label actually tell you about how a garment will wear.',
                'category' => 'Materials',
                'is_featured' => false,
                'status' => $published,
                'published_at' => now()->subDays(10),
                'seo_title' => 'Heavyweight Cotton Explained — Materials Guide',
                'seo_description' => 'Understand GSM, weave and finishing in heavyweight cotton apparel.',
                'body' => <<<'MD'
## Why weight matters

A t-shirt at 140 GSM drapes. At 280 GSM it stands. Grams per square metre is the simplest proxy for how a cotton garment will behave, and it is the first number we look at when sourcing.

### Reading the label

| Weight | Feel | Best for |
|---|---|---|
| 120–160 GSM | Light, fluid | Layering |
| 180–240 GSM | Structured | Everyday tees |
| 260 GSM + | Rigid, boxy | Outer layers, overshirts |

### Beyond GSM

- **Yarn quality.** Long-staple cotton makes smoother, stronger yarn that pills less.
- **Weave or knit.** Twill and canvas wear in like denim; jersey stays soft but can twist if poorly finished.
- **Finish.** Garment-washed cloth arrives relaxed. Raw or rigid finishes take weeks to soften — and mould to you in the process.

> A heavy fabric does not get worse with age. It gets personal.

### How ours are made

Our heavyweight range uses 300 GSM brushed-back jersey and 12 oz cotton canvas. Both are cut with generous proportions so the cloth has room to move; a heavy fabric in a tight cut feels restrictive, while the same fabric cut wide feels like armour you can sleep in.

### Break-in period

Expect the first few washes to do a lot of work. Colour settles, the hand softens, and the collar finds its shape. Do not judge the piece until you have worn it ten times.

Heavyweight cotton asks for patience, then pays it back for years.
MD,
            ],
            [
                'slug' => 'caring-for-wool-outerwear',
                'title' => 'How to Care for Wool Outerwear',
                'excerpt' => 'Brush, air, rest. A short routine that keeps a wool coat sharp for decades without a dry cleaner.',
                'category' => 'Care Guide',
                'is_featured' => false,
                'status' => $published,
                'published_at' => now()->subDays(18),
                'seo_title' => 'Wool Coat Care Guide — KRAFT Studio',
                'seo_description' => 'Simple routines to keep wool outerwear in shape: brushing, airing, storage and spot cleaning.',
                'body' => <<<'MD'
## Wool wants less than you think

Wool is naturally resistant to odour, dirt and wrinkles. Most damage to wool coats comes from over-cleaning, not from wear. Treat it gently and it will outlast almost anything else in your wardrobe.

### Weekly routine

1. **Brush** with a natural-bristle brush, working from collar to hem in the direction of the nap.
2. **Air** the coat outdoors, in shade, for an hour or two.
3. **Rest** it for a day or two between wears so fibres recover.

### Spot cleaning

- Blot, never rub, fresh stains with a clean damp cloth.
- Use a drop of wool-safe detergent for stubborn marks.
- Dry flat, away from radiators.

> Dry cleaning once a season is plenty. Once a year is often better.

### Storage

Use a wide, contoured hanger so shoulders keep their shape. Leave space between garments, and use cedar blocks rather than mothballs. Before summer storage, brush thoroughly and keep the coat in a breathable cotton bag — never plastic.

### Dealing with pilling

Light pilling is normal where fabric rubs, such as under the arms or at the cuffs. Remove it with a fabric comb or a sharp pair of small scissors. Do not pull.

### When to repair

Replace a loose button immediately and re-stitch any open seam before it spreads. A ten-minute fix now avoids a tailor's bill later.

With this routine a KRAFT wool coat should look better at year ten than most coats do at year two.
MD,
            ],
            [
                'slug' => 'ss-lookbook-notes-primary-geometry',
                'title' => 'Lookbook Notes: Primary Geometry',
                'excerpt' => 'Behind the season’s lookbook: red squares, blue circles, yellow triangles, and the concrete we shot them against.',
                'category' => 'Lookbook',
                'is_featured' => false,
                'status' => $published,
                'published_at' => now()->subDays(25),
                'seo_title' => 'Lookbook Notes: Primary Geometry',
                'seo_description' => 'Creative direction notes from the latest KRAFT Studio lookbook.',
                'body' => <<<'MD'
## The brief

Take the three primary shapes, assign each a colour, and let the clothes do the rest. That was the entire brief for this season's lookbook.

### Set and light

We shot in an unfinished concrete car park at midday. Hard sun, hard shadows, no retouching of the grey. The garments carry the saturation; the building provides the silence.

### The looks

- **Look 01 — Square.** Boxy red overshirt, wide black trousers, heavy boots.
- **Look 02 — Circle.** Blue oversized tee with a round chest print, cropped canvas pants.
- **Look 03 — Triangle.** Yellow nylon vest with angular panels over a charcoal knit.
- **Look 04 — Everything.** All three shapes in one outfit, because rules exist to be broken carefully.

> Colour is a structural element. Treat it like a beam, not a coat of paint.

### Casting

We cast friends, colleagues and neighbours of the studio rather than agency models. Different heights, different builds — the cuts are meant to work on bodies, not hangers.

### Styling notes

Keep the palette tight: at most two primaries per outfit unless you are Look 04. Let proportions do the talking — wide over narrow, heavy over light. Footwear stays plain.

### Where to see more

Every look links to its product page, and the full set sits in the archive section of the shop. Pieces from the first drop are already moving, so if something catches your eye, do not wait.
MD,
            ],
            [
                'slug' => 'washing-heavy-knits-without-ruining-them',
                'title' => 'Washing Heavy Knits Without Ruining Them',
                'excerpt' => 'Cold water, flat drying and zero wringing: the dos and don’ts for thick knitwear.',
                'category' => 'Care Guide',
                'is_featured' => false,
                'status' => $published,
                'published_at' => now()->subDays(40),
                'seo_title' => 'How to Wash Heavy Knitwear',
                'seo_description' => 'A step-by-step guide to washing and drying thick knits safely at home.',
                'body' => <<<'MD'
## Before you wash

Check whether you need to wash at all. A heavy knit that has been worn once or twice usually needs airing, not water. When it does need washing, do it by hand and do it slowly.

### Step by step

1. Fill a basin with cool water, below 30 °C.
2. Add a small amount of wool-safe detergent and swirl.
3. Submerge the knit and press gently for a few minutes. Do not scrub.
4. Drain and rinse with water of the same temperature.
5. Press out water against the side of the basin — never wring or twist.

### Drying

Roll the knit in a thick towel and press firmly to remove moisture. Then lay it flat on a dry towel or mesh rack, reshaping the sleeves and hem by hand. Keep it away from direct sun and heaters.

> Hanging a wet knit is the quickest way to stretch it by two sizes.

### Common mistakes

- Hot water, which shrinks and felts fibres.
- Fabric softener, which coats the yarn and dulls the texture.
- Tumble drying, which does both of the above at once.

### Storage

Fold knits instead of hanging them. Stack loosely in a drawer or shelf, with cedar nearby. Refold along different lines each season to avoid permanent creases.

### Quick fixes

For small pills, use a fabric comb. For a snagged loop, push it back through to the inside with a needle — never cut it.

Give a heavy knit this small amount of care and it will keep its shape for many winters.
MD,
            ],
            [
                'slug' => 'the-archive-why-we-keep-everything',
                'title' => 'The Archive: Why We Keep Every Sample',
                'excerpt' => 'Our archive room holds every prototype we have ever cut. Here is what we learn from the failures.',
                'category' => 'Essay',
                'is_featured' => false,
                'status' => $published,
                'published_at' => now()->subDays(60),
                'seo_title' => 'The KRAFT Archive',
                'seo_description' => 'Why KRAFT Studio keeps every sample and prototype it has ever made.',
                'body' => <<<'MD'
## A room full of mistakes

At the back of the studio there is a room with floor-to-ceiling steel shelving. Every prototype, every failed collar, every sleeve that was too long — it is all there, labelled and dated. We call it the archive.

### Why keep it

- **Memory.** Without a record, we repeat our errors.
- **Comparison.** Holding the 2022 pocket next to the 2025 pocket shows how our thinking has changed.
- **Honesty.** It reminds us that good design is mostly editing.

### What the archive taught us

1. Fewer seams usually means a stronger garment.
2. Pockets should be sized to hands first and phones second.
3. If a detail cannot be explained in one sentence, remove it.

> The best piece in the room is rarely the loudest. It is the one nobody can improve.

### From shelf to shop

Some archive pieces return as limited re-editions. We pull the original pattern, correct what we got wrong, and produce a short run. If you see an *Archive* tag in the shop, that is what you are looking at.

### Sustainability, quietly

Keeping samples is also a way to avoid waste. Prototypes are cut up for testing, rebuilt as new samples, or donated to local design schools. Nothing leaves the building without a second purpose.

### Visit

The archive is not open to the public, but we run a few studio days each year. Subscribe to the newsletter and you will hear first.
MD,
            ],
            [
                'slug' => 'internal-draft-autumn-capsule',
                'title' => 'Autumn Capsule (Draft)',
                'excerpt' => 'Work in progress: notes for the autumn capsule.',
                'category' => 'Lookbook',
                'is_featured' => false,
                'status' => Article::STATUS_DRAFT,
                'published_at' => null,
                'body' => "## Draft\n\nNot ready for the storefront yet.",
            ],
            [
                'slug' => 'scheduled-winter-materials-preview',
                'title' => 'Winter Materials Preview (Scheduled)',
                'excerpt' => 'A preview of the fabrics arriving next season.',
                'category' => 'Materials',
                'is_featured' => false,
                'status' => $published,
                'published_at' => now()->addDays(30),
                'body' => "## Coming soon\n\nThis article goes live in the future.",
            ],
        ];
    }
}
