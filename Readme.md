# Running the project

Start the Database:
````
yarn docker:db
````
Start the Backend Service:
````
yarn start
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
## Database
I decided to use a simple mysql database for this project. I created a docker-compose file wich can be used to start the database. The database is exposed on port 3306 and can be accessed with the following credentials:
````
database: colors
user: root
password: frontify
````
### Database Connection
I used the MySQLi PHP extension to connect to the database.
Some of the Queries are not yet prepared statements. This is something that needs be rewritten before this code can be used in production.
## Tests
I invested more time in the frontend implementation and therefore did not yet write tests in the backend.