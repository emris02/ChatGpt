-- 🔍 Voir les employés enregistrés
SELECT * FROM employes;

-- 🔍 Voir les derniers pointages
SELECT * FROM pointages ORDER BY date_heure DESC LIMIT 10;

-- ⏱️ Voir les employés arrivés en retard (exemple à partir de 09h00)
SELECT e.nom, e.prenom, p.heure_arrivee
FROM employes e
JOIN pointages p ON e.id = p.employe_id
WHERE p.heure_arrivee > '09:00:00'
ORDER BY p.heure_arrivee DESC;

-- 📊 Total de jours pointés par employé
SELECT e.nom, e.prenom, COUNT(p.id) AS jours_pointés
FROM employes e
LEFT JOIN pointages p ON e.id = p.employe_id
GROUP BY e.id
ORDER BY jours_pointés DESC;
