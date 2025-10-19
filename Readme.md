yarn docker:db
yarn start
# Running the project

Start the Database (optional — the app uses SQLite by default):
````
yarn docker:db
````

Start the Backend Service:
````
composer install
./run.sh
````

Or run the server with a MySQL backend:
````
DB_DRIVER=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=colors DB_USERNAME=root DB_PASSWORD=frontify yarn start
````

# Approch for Solution
## Usage of GraphQL
In the discussion with Marc, a big part of our discussion was designing optimized GraphQL APIs. He told me that this is what frontify is using for the majority of their APis and there are only very few REST APIs left wich are not yet migrated.

After looking at this provided PHP backend project i noticed that it was targeted towards developing a REST API.
After asking Tom about it, he mentioned that this is part of the reason why the task template is being revamped and that i should simply document all my changes.

Because of that, I decided to rewrite the backend code to utilize GraphQL instead of REST. This allows for the code to be aligned with the current frontify tech stack.
It gives me the possibility to show cool graphql features on the frontend side of things.

## Library
I used the [HTTP / GraphQL Library ](https://github.com/webonyx/graphql-php) Library for the GraphQL Spec implementation. Because of the scope of this little project, i decided not to reinvent the wheel 🤣

## Structure

I decided to keep this very simple and basic and use some more time for the frontend implementation. This code still has lots of room for improvement.

### Server

I'm using the StandardServer from the GraphQL Lib to serve GET AND POST requests. I'm using a manual catch for option requests wich are required to be returned for graphql to work properly. This can defenitely be improvent with a proper HTTP handling.

### Schema
#### Object Types
##### Color

````
type Color {
    id: Int
    name: String
    value: String
}
````
##### Success
This Object can later be extended to provide more information about the success or error of the mutation
````
type Success {
    success: Boolean
}
````
#### Queries
##### colors
````
colors(limit: Int): [Color]
````
##### color
````
color(limit: Int, offfset: Int ): Color
````
#### Mutations
##### addColor
````
addColor(name: String, value: String): Color
````
##### updateColor
````
updateColor(id: Int, name: String, value: String): Color
````
##### deleteColor
````
deleteColor(id: Int): Success
````
database: colors
## Database
The project now uses a PDO-backed helper (`src/Database.php`). By default the app uses an embedded SQLite database stored at `data/colors.db`. For production or integration testing you may switch to MySQL/MariaDB by setting the environment variables `DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

Migrations and seeding are performed automatically at startup via `Database::runMigrations()` — the code will seed from `colors.json` when present and fall back to a small default set.
## Tests
Lightweight unit tests were added for the color utilities. Run the test suite with:

````
php ./vendor/bin/phpunit --colors=always tests/
````