# catalyse-access

Just a <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/301">301</a> redirect to catalyse-buyer.epfl.ch.

## deployment

This project is hosted on a EPFL LAMP server and uses [WebDAV] to manage its
files. Running the commands `make dav-sync` will interactivly sync files to
the remote. If needed, you can list files with `make lsf` and directories with
`make lsd`.

[WebDAV]: https://en.wikipedia.org/wiki/WebDAV
