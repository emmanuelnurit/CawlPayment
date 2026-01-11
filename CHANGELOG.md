# Changelog - CawlPayment

Toutes les modifications notables de ce module sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Versionnement Sémantique](https://semver.org/lang/fr/).

---

## [1.0.0] - 2024-12-15

### Ajouté

#### Fonctionnalités principales
- **Intégration Worldline/CAWL Solutions** via le SDK PHP officiel
- **Hosted Checkout** : redirection vers la page de paiement sécurisée Worldline
- **Support multi-environnement** : basculement Test/Production
- **Dashboard de test API** dans l'administration

#### Méthodes de paiement (30+)
- **Cartes bancaires** : Visa, Mastercard, CB, American Express, Maestro, JCB, Diners Club, Discover
- **Portefeuilles numériques** : PayPal, Apple Pay, Google Pay, WeChat Pay, Alipay
- **Virements bancaires** : iDEAL, Bancontact, Giropay, EPS, Przelewy24, Sofort, Multibanco, TWINT
- **Paiement fractionné** : Klarna (Pay Now, Pay Later, Slice It), Oney 3x/4x
- **Titres restaurant** : Edenred, Sodexo, Up Déjeuner

#### Webhooks
- Réception des notifications de paiement en temps réel
- Validation des signatures HMAC-SHA256
- Mise à jour automatique du statut des commandes

#### Multi-langue
- Français (fr_FR)
- Anglais (en_US)
- Espagnol (es_ES)
- Italien (it_IT)
- Allemand (de_DE)

#### Intégrations
- Compatible avec le module OpenApi de Thelia
- Affichage des logos des méthodes de paiement depuis l'API
- Support des options de paiement via `PaymentModuleOptionEvent`

#### Administration
- Interface de configuration complète avec onglets
- Test de connexion API intégré
- Chargement dynamique des méthodes de paiement
- Configuration des limites de montant
- Journalisation configurable

#### Sécurité
- Validation HMAC-SHA256 des webhooks
- Rejet des webhooks sans secret en production
- Messages d'erreur génériques (pas d'exposition des détails internes)
- Journalisation sécurisée via Thelia Tlog
- Injection de dépendances Symfony

#### Base de données
- Table `cawl_transaction` pour le suivi des transactions
- Enregistrement des requêtes/réponses API
- Historique des statuts de paiement

#### Documentation
- README.md complet avec guide de configuration
- INSTALL.md avec procédure d'installation détaillée
- CHANGELOG.md pour le suivi des versions

### Technique

#### Architecture
- Compatible Thelia 2.6 / Symfony 6.4
- Utilisation de Propel ORM pour la persistance
- Services déclarés dans le conteneur Symfony
- EventSubscriber pour l'intégration OpenAPI

#### Fichiers principaux
```
CawlPayment.php              - Classe principale du module
Service/CawlApiService.php   - Service d'interaction avec l'API CAWL
Controller/Front/            - Contrôleurs paiement et webhook
Controller/Admin/            - Contrôleurs administration
EventListeners/              - Écouteurs pour OpenAPI
```

---

## [Unreleased]

### Prévu
- Support des remboursements partiels depuis l'admin Thelia
- Capture différée des paiements
- Rapports de transactions dans l'administration
- Support de PayByLink (liens de paiement)
- Intégration du widget de paiement embarqué

---

## Notes de mise à jour

### De 0.x vers 1.0.0

Cette version est la première release stable. Si vous utilisiez une version de développement :

1. Sauvegardez votre configuration actuelle
2. Désactivez l'ancien module
3. Supprimez les anciens fichiers
4. Installez la version 1.0.0
5. Réactivez et reconfigurez le module

### Migration de base de données

La table `cawl_transaction` est créée automatiquement lors de l'activation. Aucune migration manuelle n'est nécessaire.

---

## Versionnement

Ce module suit le versionnement sémantique :

- **MAJOR** (X.0.0) : Changements incompatibles avec les versions précédentes
- **MINOR** (0.X.0) : Nouvelles fonctionnalités rétrocompatibles
- **PATCH** (0.0.X) : Corrections de bugs rétrocompatibles

---

## Liens

- [Documentation CAWL](https://docs.direct.worldline-solutions.com/)
- [SDK PHP Worldline](https://github.com/Worldline-Global-Collect/connect-sdk-php)
- [Documentation Thelia](https://doc.thelia.net/)
