# 00xx - BIOS Zone (Pre-Boot / Environment)

Loads before the OS. The `j0` engine does not typically occupy this zone (its true kernel lives in `j0/core/` outside module scanning). This zone is the secret weapon of Userland (`src/00xx-...`) to inject polyfills, global environment variables, or early patches before the OS boots.

This architectural pattern coincides with that of the operating systems of the 70s and 80s (UNIX, VMS, DOS) whose heirs are present in the devices we use today. This layer provides extremely low-level pre-execution routines below the main services and visual layers.