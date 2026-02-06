# Pinterest SEO Guide (ClickBank / English)

This guide is a lightweight SOP for writing Pinterest copy for ClickBank-style offers in English (US/global audience).

## 1) Ideal Pinterest Pin Copy

### Title
- **Length:** 40–70 characters (less truncation on mobile).
- **Formula:** `[Primary Keyword] + [Benefit/Angle] + [Specific Hook]`
- **Examples:**
  - Weak: `Mobility Program`
  - Better: `Mobility Routine for Stiff Hips (Beginner-Friendly)`

### Description
- **This project stores:** 7–15 words (single sentence, no hashtags).
- Keep it natural, benefit-led, and non-spammy.
- Avoid overclaims (no guarantees).

### Keywords
- Use **5–8** keywords.
- Mix short-tail and long-tail queries.

## 2) Manual Prompt Template (Optional)

**Title prompt**
> Generate 5 Pinterest titles for {OFFER_NAME}. 40–70 characters. Include the primary keyword. English only. No clickbait.

**Description prompt**
> Write a one-sentence Pinterest description for {OFFER_NAME}. 7–15 words. English only. No hashtags. Neutral, compliant phrasing.

## 3) Board Name Examples

- `Mobility & Flexibility`
- `Healthy Habits at Home`
- `Beginner Fitness Routines`
- `Self Improvement Tips`
- `Simple Meal Ideas`

## 4) Safety / Compliance

- Avoid medical claims (no “cure”, “treat”, “guaranteed results”).
- Avoid income claims (no “make $X fast”, “guaranteed income”).
- Keep language honest and specific.

## 5) System Flow

1. User inputs offer details + image + affiliate link.
2. `PinterestGenerator.php` sends a strict JSON prompt to Gemini.
3. The app stores generated fields and shows a preview page you can pin.
