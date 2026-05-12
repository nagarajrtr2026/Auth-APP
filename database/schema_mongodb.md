# MongoDB schema (profiles)

**Database:** `mt_auth` (configurable in `php/config.php`)

**Collection:** `profiles`

Each document stores extended profile fields linked to MySQL `users.id`.

```javascript
{
  "user_id": 1,           // int — matches MySQL users.id
  "tenant_id": "default", // string — matches MySQL users.tenant_id
  "age": 22,              // int | null
  "dob": "2004-06-15",    // string ISO date | null
  "contact": "+1 555...", // string | null
  "bio": "Short bio text" // string | null
}
```

**Recommended index** (run once in mongosh or Compass):

```javascript
use mt_auth
db.profiles.createIndex({ user_id: 1, tenant_id: 1 }, { unique: true })
```
