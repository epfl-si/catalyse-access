# catalyse-access

A intercalary page to handle access to "catalyse".

## prerequisites

- access to `/keybase/team/epfl_catalyse/`
- docker / docker-compose / rclone / EPFL VPN

## development

To do once:
1. get your own copy with `git clone`
3. add the line `127.0.0.1	catalyse-dev.epfl.ch` in your `/etc/hosts`
4. create the `app/.htaccess` file from the `app/sample.htaccess` or just use
   `/keybase/team/epfl_catalyse/htaccess`.

Then, whenever you want to hack on it:
1. run `make up` to start the project
2. head to http://catalyse-dev.epfl.ch:8123

## deployment

This project is hosted on a EPFL LAMP server and uses [WebDAV] to manage its
files. Running the commands `make dav-sync` will interactivly sync files to
the remote. If needed, you can list files with `make lsf` and directories with
`make lsd`.

## doc

- EPFL oAuth2: https://confluence.epfl.ch:8443/display/ideverpmd/Demande+de+client+OAuth2
- oAuth2 client: https://oauth2-client.thephpleague.com/


[WebDAV]: https://en.wikipedia.org/wiki/WebDAV
