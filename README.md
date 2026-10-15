# Venusian PHP Framework

[![Latest Version on Packagist](https://img.shields.io/packagist/v/venusian/framework.svg)](https://packagist.org/packages/venusian/framework)
[![Total Downloads](https://img.shields.io/packagist/dt/venusian/framework.svg)](https://packagist.org/packages/venusian/framework)
[![License](https://img.shields.io/packagist/l/venusian/framework.svg)](https://packagist.org/packages/venusian/framework)

## About Venusian

Venusian is a PHP application framework with expressive, elegant syntax for building applications that use windowed GUIs, human inputs, and integrated circuits. We believe development must be an enjoyable and creative experience to be truly fulfilling. Venusian takes the pain out of development by easing common tasks used in embedded and edge projects, such as:

- [Powerful dependency injection container](https://venusian.projectsaturnstudios.com/docs/chassis)
- Multiple back-ends for [cache](https://venusian.projectsaturnstudios.com/docs/cache) storage
- Database agnostic [schema migrations](https://venusian.projectsaturnstudios.com/docs/migrations)
- [Robust background job processing](https://venusian.projectsaturnstudios.com/docs/queues)
- [Computer CLI](https://venusian.projectsaturnstudios.com/docs/computer) and [Probe REPL](https://venusian.projectsaturnstudios.com/docs/computer#probe)

Venusian is accessible, powerful, and provides tools required for large, robust applications — including driving GPIO, I2C, SPI, UART, and related hardware through companion packages.

## Learning Venusian

Venusian has documentation and guides on the [Venusian website](https://venusian.projectsaturnstudios.com/docs), making it a breeze to get started with the framework.

## Open Knowledge Format (`.okf`)

This skeleton ships with a package-root [`.okf/`](.okf/) knowledge bundle for agents and humans working on your app. It is **included** when you create a project from this repository (`composer create-project` / Git archive) so local development keeps that context.

When you are ready to deploy the app to a target (for example an SBC), exclude the knowledge bundle from the deploy archive by adding the following line to your project's `.gitattributes`:

```gitattributes
/.okf export-ignore
```

That keeps `.okf` in your development clone while omitting it from `git archive` / Composer-style export artifacts used for deployment.

## Contributing

Thank you for considering contributing to Venusian! The contribution guide can be found in the [Venusian documentation](https://venusian.projectsaturnstudios.com/docs/contributions).

## Code of Conduct

In order to ensure that the Venusian community is welcoming to all, please review and abide by the [Code of Conduct](https://venusian.projectsaturnstudios.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/VenusianPHP/framework/security/policy) on how to report security vulnerabilities.

## License

Stargazer is free software licensed under the [GNU General Public License v3.0](LICENSE), with an additional permission to ship it compiled together with the PHP runtime and its extensions. See [LICENSE](LICENSE).
