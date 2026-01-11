# Guide d'installation - CawlPayment pour Thelia 2.6

Ce guide détaille les étapes d'installation et de configuration initiale du module CawlPayment.

## Sommaire

1. [Prérequis](#1-prérequis)
2. [Installation du module](#2-installation-du-module)
3. [Création du compte CAWL](#3-création-du-compte-cawl)
4. [Configuration initiale](#4-configuration-initiale)
5. [Configuration des webhooks](#5-configuration-des-webhooks)
6. [Test de l'installation](#6-test-de-linstallation)
7. [Mise en production](#7-mise-en-production)

---

## 1. Prérequis

### Environnement technique

| Composant | Version requise |
|-----------|-----------------|
| Thelia | 2.6.x |
| PHP | 8.2+ |
| MySQL/MariaDB | 8.0+ / 10.3+ |
| Extensions PHP | curl, json, openssl, mbstring |

### Vérification des prérequis

```bash
# Vérifier la version PHP
php -v

# Vérifier les extensions
php -m | grep -E "(curl|json|openssl|mbstring)"

# Vérifier la version Thelia
php bin/console about
```

### Compte CAWL Solutions

Vous devez disposer d'un compte CAWL Solutions avec :
- Un PSPID (identifiant marchand)
- Des identifiants API (clé + secret)
- Un contrat pour les méthodes de paiement souhaitées

---

## 2. Installation du module

### Option A : Installation manuelle

#### Étape 1 : Téléchargement

Téléchargez le module depuis le dépôt officiel ou copiez le dossier `CawlPayment` dans :

```
/votre-thelia/local/modules/CawlPayment/
```

#### Étape 2 : Vérification de la structure

Assurez-vous que la structure suivante est présente :

```
local/modules/CawlPayment/
├── Config/
│   ├── config.xml
│   ├── module.xml
│   ├── routing.xml
│   ├── schema.xml
│   └── TheliaMain.sql
├── Controller/
├── EventListeners/
├── I18n/
├── Model/
├── Service/
├── templates/
├── CawlPayment.php
└── composer.json
```

#### Étape 3 : Installation des dépendances

```bash
cd /votre-thelia
composer require online-payments/sdk-php
```

#### Étape 4 : Activation du module

```bash
# Via la console
php bin/console module:activate CawlPayment

# OU via l'interface admin
# Administration > Modules > CawlPayment > Activer
```

#### Étape 5 : Création des tables

Les tables sont créées automatiquement lors de l'activation. Pour vérifier :

```bash
php bin/console propel:sql:build
```

#### Étape 6 : Vidage du cache

```bash
php bin/console cache:clear
```

### Option B : Installation via Composer

```bash
composer require cawl/thelia-payment
php bin/console module:activate CawlPayment
php bin/console cache:clear
```

---

## 3. Création du compte CAWL

### Compte de test (Sandbox)

1. Rendez-vous sur [https://merchant.preprod.direct.worldline-solutions.com](https://merchant.preprod.direct.worldline-solutions.com)

2. Cliquez sur **"Créer un compte"** ou contactez CAWL Solutions

3. Une fois connecté, récupérez :
   - Votre **PSPID** (identifiant marchand)
   - Dans **Configuration > API Keys** : créez une paire clé/secret

### Compte de production

1. Contactez CAWL Solutions pour obtenir un contrat de production
2. Accédez au [Portail Production](https://merchant.direct.worldline-solutions.com)
3. Récupérez vos identifiants de production

---

## 4. Configuration initiale

### Accès à la configuration

1. Connectez-vous à l'administration Thelia
2. Allez dans **Modules > CawlPayment > Configurer**

### Onglet "Identifiants"

#### 1. Environnement

Sélectionnez **Test** pour commencer.

#### 2. PSPID

Entrez votre identifiant marchand CAWL.

#### 3. Identifiants de test

| Champ | Où le trouver |
|-------|---------------|
| Test API Key | Portail CAWL > Configuration > API Keys |
| Test API Secret | Généré avec la clé API |

#### 4. Test de connexion

Cliquez sur **"Tester la connexion"**. Vous devez voir :

```
✓ Connexion réussie !
Environnement : Test
Endpoint : https://payment.preprod.direct.worldline-solutions.com
```

### Onglet "Méthodes de paiement"

1. Attendez le chargement des méthodes depuis l'API
2. Cochez les méthodes que vous souhaitez proposer
3. Les méthodes disponibles dépendent de votre contrat CAWL

### Onglet "Options"

| Option | Recommandation |
|--------|----------------|
| Activer les logs | ✓ Oui (en développement) |
| Montant minimum | Selon vos besoins |
| Montant maximum | Selon vos besoins |

### Sauvegarde

Cliquez sur **"Enregistrer la configuration"**.

---

## 5. Configuration des webhooks

Les webhooks sont **essentiels** pour confirmer les paiements de manière fiable.

### Étape 1 : Récupérer l'URL du webhook

Dans la configuration CawlPayment, section "Webhook", copiez l'URL :

```
https://votre-site.com/cawlpayment/webhook
```

### Étape 2 : Configurer dans le portail CAWL

1. Connectez-vous au [Portail CAWL Test](https://merchant.preprod.direct.worldline-solutions.com)
2. Allez dans **Configuration > Webhooks**
3. Cliquez sur **"Add webhook endpoint"**
4. Configurez :

| Champ | Valeur |
|-------|--------|
| URL | `https://votre-site.com/cawlpayment/webhook` |
| Events | Tous les événements de paiement |

5. Après création, copiez :
   - **Webhook Key ID**
   - **Webhook Secret**

### Étape 3 : Enregistrer dans Thelia

1. Retournez dans la configuration CawlPayment
2. Collez la **Webhook Key** et le **Webhook Secret** dans les champs correspondants
3. Sauvegardez

### Vérification

Le webhook est correctement configuré si :
- L'URL est accessible en POST depuis l'extérieur
- Le secret correspond à celui du portail CAWL

---

## 6. Test de l'installation

### Test 1 : Vérification de la connexion API

1. Configuration CawlPayment > Bouton "Tester la connexion"
2. Résultat attendu : `Connexion réussie !`

### Test 2 : Vérification des méthodes de paiement

1. Onglet "Méthodes de paiement"
2. Les méthodes doivent s'afficher automatiquement
3. Si vide : vérifiez vos identifiants API

### Test 3 : Dashboard de test

1. Accédez à `/admin/cawlpayment/test-dashboard`
2. Cliquez sur "Test Connection" → Doit retourner `success: true`
3. Cliquez sur "Get Payment Products" → Liste des méthodes
4. Cliquez sur "Create Test Checkout" → Crée un checkout de 10€

### Test 4 : Paiement de bout en bout

1. Créez une commande test sur votre boutique
2. Sélectionnez CawlPayment comme mode de paiement
3. Choisissez une méthode (ex: Visa)
4. Sur la page Worldline, utilisez :

| Champ | Valeur |
|-------|--------|
| Numéro de carte | `4012 0000 3333 0026` |
| Date d'expiration | Toute date future |
| CVV | `123` |

5. Validez le paiement
6. Vérifiez :
   - Redirection vers la page de confirmation
   - Commande passée en statut "Payé"
   - Log dans `var/log/cawlpayment.log`

### Test 5 : Webhook

1. Effectuez un paiement test
2. Vérifiez dans les logs :
```
[CawlPayment Webhook] Received webhook notification
[CawlPayment Webhook] Order #XXX marked as PAID
```

---

## 7. Mise en production

### Checklist avant mise en production

- [ ] Compte CAWL production créé
- [ ] Identifiants production configurés
- [ ] Webhook production configuré
- [ ] HTTPS actif sur le site
- [ ] Tests effectués avec succès
- [ ] Logs désactivés ou niveau réduit
- [ ] Méthodes de paiement vérifiées

### Basculer en production

1. **Configuration > Identifiants**
2. Remplissez les **identifiants de production** :
   - Production API Key
   - Production API Secret
   - Production Webhook Key
   - Production Webhook Secret

3. Changez l'environnement de **Test** à **Production**

4. **Testez la connexion** en mode production

5. Configurez le webhook de production dans le [Portail Production](https://merchant.direct.worldline-solutions.com)

6. **Sauvegardez**

### Vérification post-production

1. Effectuez un **petit paiement réel** (1€)
2. Vérifiez la réception des fonds
3. Testez un remboursement depuis le portail CAWL
4. Vérifiez la mise à jour du statut dans Thelia

---

## Résolution des problèmes courants

### Le module n'apparaît pas dans la liste

```bash
php bin/console module:refresh
php bin/console cache:clear
```

### Erreur "Class not found"

```bash
composer dump-autoload
php bin/console cache:clear
```

### Les tables ne sont pas créées

```bash
php bin/console propel:model:build
php bin/console propel:sql:build
php bin/console propel:sql:insert
```

### Erreur 500 lors du paiement

1. Vérifiez les logs PHP
2. Vérifiez `var/log/cawlpayment.log`
3. Activez le mode debug Symfony temporairement

---

## Support

- **Documentation** : Voir [README.md](README.md)
- **Email** : support@cawl-solutions.com
- **Portail CAWL Test** : https://merchant.preprod.direct.worldline-solutions.com
- **Portail CAWL Production** : https://merchant.direct.worldline-solutions.com
