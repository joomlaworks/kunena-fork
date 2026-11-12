# Blackadder

Kunena's default template, by JoomlaWorks Ltd.: monochrome, no Bootstrap, one stylesheet (`assets/css/blackadder.css`)
and one plain-JavaScript file (`assets/js/blackadder.js`). It takes the site template's fonts and colours and follows its
light/dark scheme by itself; the variables below override that.

## Colours

Set any of these on `:root` (or any ancestor of the forum) in your site template's CSS. All are optional: leave
`--kunena-background` unset to keep the page's own background behind the forum.

```css
:root {
    --kunena-background:         #ffffff;
    --kunena-foreground:         #09090b;
    --kunena-muted:              #f4f4f5;
    --kunena-muted-foreground:   #71717a;
    --kunena-border:             #e4e4e7;
    --kunena-primary:            #18181b;
    --kunena-primary-foreground: #fafafa;
    --kunena-ring:               #a1a1aa;
    --kunena-destructive:        #dc2626;
    --kunena-radius:             0.5rem;
}
```

Dark mode is detected from the page's background; `data-theme="dark"` or `data-scheme="dark"` on any ancestor (e.g.
`<html>`) also switch to it, and `"auto"` follows the device.

## Categories

Category rows, thread rows and threads carry `kb-cat--<category alias>`:

```css
.kb-cat--announcements {--kb-cat:#b45309;}
```

## Your own additions

Create `assets/css/custom.css`: it is loaded after the template's stylesheet.
