# README #
 This README would normally document whatever steps are necessary to get your application up and running.

## Requirements 

docker
docker-compose
composer version 2.5.8 (stable channel)
  
### Instructions for starting the application locally ###

    git clone git@bitbucket.org:whale-studio/dog.git

Inside dog directory launch composer install 

    composer install

 If you have an error like:

    drush/drush[12.1.0, ..., 12.1.3] require composer-runtime-api ^2.2 -> found composer-runtime-api[2.1.0] but it does not match the constraint.
    Root composer.json requires drush/drush ^12.1 -> satisfiable by drush/drush[12.1.0, 12.1.1, 12.1.2, 12.1.3].
 
run the following commands:

    composer clearcache
    composer selfupdate

and after again

    composer install

At the end, unpack the db inside the mariadb-init directory
You will need to have the dog.sql file
Start docker compose

    docker-compose up -d

Wait for the db to load
Make sure you see the application at

    http://localhost:8000

Now you have to compile the graphic theme, to do this you can log into the php container

    docker exec -it dog_php sh

Inside the php shell run the following commands:

    sudo apk update && sudo apk add npm

 Move to directory

    cd web/themes/custom/italiagov
    
run npm build and clear cache

    npm install
    npm run build:prod
    drush cr

Set the latest configuration and clear the cache

    drush cim
    drush cr

To login in the backend go to the url reset credentials with the command

    drush upwd admin admin

for more information on compiling the theme and its structure look here:
    https://git.drupalcode.org/project/bootstrap_italia/-/blob/8.x-0.24/README.md

That's all