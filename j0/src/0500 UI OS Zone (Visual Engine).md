# 05xx - UI OS Zone (Visual Engine)

The interface engine and declarative interaction layer. Handles physical manipulation, CSS tokens, base components, and DOM behaviors. Everything here assumes the Base OS (Zone `01xx`) is already initialized and ready.

This architectural pattern coincides with that of the operating systems of the 80s and 90s (Windows, MacOS X) layered on top of 70's architecture (UNIX, DOS, VMS/WinNT). The UI OS layer provides visual and interactive services, which in the case of Windows was born precisely as a visual layer "work environment" on top of DOS, and in the case of macOS became so with Mac OS X over Darwin, the derivative of BSD UNIX, etc. (the architecture of macOS Classic does not serve us for this metaphor, so we leave it aside).