Chaque modification de hook ou de base de données doit être executées dans l'install native du module et dans un upgrade.
Les controllers admin doit être en prestashop modern (symfony)

# Dévelopment requirement
- utilise le sous agent pour que le code généré soit au format PSR-12
- les projets tournent tous avec docker donc si tu dois executer une commande lié projet (php bin/console par exemple) demande moi le container name du projet
- utilise le camelCase
- les requêtes SQL utilise DBQuery si je ne te dis pas l'inverse
- Les tâches planifié sont faites par des Commands symfony

### TL;DR - Mes priorités absolues

1. **Complexité cyclomatique < 10** par méthode
2. **Imbrication max 2-3 niveaux** (privilégier early returns)
3. **Typage strict systématique** (paramètres, retours, propriétés)
4. **Zéro tolérance** : pas de dump(), code mort, ou commentaires obsolètes
5. **Sécurité first** : validation des inputs, pas de credentials en dur

Tu dois garder une compatibilité large sur le module php 7.1 minimume et prestashop 1.7.6

Fais umoi une classe de reponsabilité par hook : lorsque tu créées ou utilise un hook dans la classe principale, celui ci doit avoir une classe dans src/Hooks/
Lorsque tu auras fini, tu me fera le readme via l'agent claude et tu remplira le CHANGELOG avec l'agent adequat
