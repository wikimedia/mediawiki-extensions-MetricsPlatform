FROM docker-registry.wikimedia.org/repos/data-engineering/mpic:latest

WORKDIR /srv/service

# Part 2 of disabling authentication: Use the development build of the frontend
RUN [ "npm", "run", "build-frontend-dev" ]

ENTRYPOINT [ "node", "./server.js" ]
