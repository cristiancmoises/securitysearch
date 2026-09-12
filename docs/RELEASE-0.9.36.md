# SecuritySearch v0.9.36

Image cards previously chose the minimum known source area. A 1 × 1 placeholder after a
320 × 240 thumbnail could therefore be displayed as the preview of a 1600 × 1200 original.
The new selector considers supplied alternatives (the first valid source remains original).
It prefers the smallest known preview reaching either 236-pixel width or 180-pixel height,
with both edges above 16 pixels; otherwise the last unknown-size preview, largest non-tiny
undersized preview, then original. Ties retain source order. Unknown size is not proof of
content quality. Very narrow valid artwork may intentionally fall back to its original.

Only the first 32 raw source entries are inspected, including invalid/duplicate entries.
A useful source after that bound is not considered. The page remains limited to 24 cards;
no extra proxy candidate, retry, provider call or query/image cache is introduced.

The PNG validator/proxy, provider adapters, motion/infinite-scroll code, local pictures,
manual benchmark, cleanup guard, deployment transaction and rollback policy are unchanged.
Asset version is 40. Existing 102 audit commands remain, followed by four new required ones.

Use the separate checksum-verified Fish deployment and publication launchers in ~/Downloads.
Supported exact baselines: corrected v0.9.30 and v0.9.31 through v0.9.35. Applying the exact
v0.9.36 twice is a no-op. Unknown/dirty trees and conflicting tags are preserved and refused.
Native audit, serving-image identity, direct Google web/images, RSS and Binternet gates are
mandatory. A local renderer fixture is not provider acceptance or proof of a live deployment.
