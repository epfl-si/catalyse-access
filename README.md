# catalyse-access

A intercalary page to handle access to "catalyse".

## prerequisites

- access to `/keybase/team/epfl_catalyse/oauth.info`
- docker / docker-compose

## development

To do once:
1. get your own copy with `git clone`
3. add the line `127.0.0.1	catalyse-access-dev.epfl.ch` in your `/etc/hosts`
4. create the `.env` file from the `.env.sample`

Then, whenever you want to hack on it:
1. run `make up` to start the project

## doc

- EPFL oAuth2: https://confluence.epfl.ch:8443/display/ideverpmd/Demande+de+client+OAuth2
- oAuth2 client: https://oauth2-client.thephpleague.com/
