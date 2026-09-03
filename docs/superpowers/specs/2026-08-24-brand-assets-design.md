# SellerScope Brand Assets

## Goal

Use the approved PNG files from `public/images/brand` as the application logo and browser icons without duplicating or transforming the assets.

## Mapping

- `sellerscope-logo-horizontal.png` is the full SellerScope logo.
- `sellerscope-mark.png` is the compact logo used when the sidebar is collapsed.
- `sellerscope-favicon-16.png` is the 16x16 browser icon.
- `sellerscope-favicon-32.png` is the 32x32 browser icon.
- `sellerscope-mark.png` is the Apple touch icon because no separate touch icon asset is supplied.

## Implementation

`BrandLogo` keeps its existing Inertia link, focus treatment, compact mode, and accessible label. Text-based branding is replaced with `<img>` elements that use stable dimensions and `object-contain` to avoid layout shift or distortion.

The root Blade layout replaces references to the old root-level favicon files with explicit links to the approved PNG assets. No asset copies, generated derivatives, dependency changes, or design changes are introduced.

## Verification

- Run TypeScript, ESLint, Prettier, and production build checks.
- Open the application shell demo and an authentication page.
- Verify full and compact logo rendering at desktop and responsive widths.
- Verify favicon requests succeed and browser console contains no relevant warnings or errors.
