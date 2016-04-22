# Fork summary

I was wondering to apply specific srcset definitions for images based on the colPos they or their respective content element belong to.
This is a quick hack, as I'm not a very experienced TYPO3 developer I cannot say how stable this or reliable this solution is.

This quick has been only tested for layoutKey = srcset

## Example settings

I think from the following settings, you get the idea of the configuration. If there is no definition for a colPos where an image
has been placed, the configuration of colPos.0 will be used instead.

```
tt_content.textmedia.settings.responsive_image_rendering {
    layoutKey = srcset

    sourceCollection {
        colPos.0 {
            small {
                width = 400
                mediaQuery = (max-device-width: 400px)
                dataKey = small
                srcset = 400w
                sizes = 50vw
            }

            large {
                width = 1200
                mediaQuery = (min-device-width: 1000px)
                dataKey = large
                srcset = 800w
                sizes = 100vw
            }
        }

        colPos.2 {
            small {
                width = 100
                mediaQuery = (max-device-width: 400px)
                dataKey = small
                srcset = 400w
            }

            large {
                width = 200
                mediaQuery = (min-device-width: 1000px)
                dataKey = large
                srcset = 800w
            }
        }
    }
}
```
