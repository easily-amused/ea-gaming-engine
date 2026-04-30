# Tap-a-Question Sprites

This directory contains placeholder sprite information for the Tap-a-Question game.

## Required Sprites

- `hole.png` - Hole sprite (circular dark hole in ground)
- `target.png` - Pop-up target sprite (creature or icon that emerges from the hole)
- `background.png` - Game background (grass field or playful surface)

## Sprite Specifications

### hole.png
- Size: 60x60 pixels
- Format: PNG with transparency
- Content: Dark circular hole with shaded edges
- Style: Cartoon-like, matching theme aesthetic

### target.png
- Size: 50x50 pixels
- Format: PNG with transparency
- Content: Friendly pop-up character or icon
- Style: Friendly cartoon appearance

### background.png
- Size: 800x600 pixels
- Format: PNG or JPG
- Content: Grassy field or playful surface
- Style: Colorful, child-friendly environment

## Fallback Graphics

The game will use fallback colored shapes if these sprites are not available:
- Holes: Brown circles (0x8B4513)
- Background: Theme-based color fill
- Answer containers: Rounded rectangles with theme colors

## Theme Integration

Sprites should be designed to work with all three themes:
- Playful: Bright, vibrant colors
- Minimal Pro: Clean, professional aesthetic
- Neon Cyber: Futuristic, glowing effects

Consider creating theme-specific variations or using programmable shaders for theme adaptation.
