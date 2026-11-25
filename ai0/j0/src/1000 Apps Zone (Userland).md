# 1xxx - Apps Zone (Userland)

Tools, pages, and specific business logic. The `j0` repository WILL NEVER include a module with a number higher than `0999`. This zone (from `1000` to `9999`) is the exclusive property of the host project and userland applications.

This architectural pattern coincides with that of the operating systems we use today. This layer represents user space, where isolated applications run on top of the provided OS APIs without altering the underlying kernel.