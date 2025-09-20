# ChatGPT Quest Duration Addendum Prompt

**Add this to your existing ChatGPT quest generation prompt:**

---

## IMPORTANT ADDITION - Quest Duration:

Please add an estimated duration field to the quest JSON. Include a `duration_minutes` field in the main quest object that estimates how long it would take an average group to complete the entire quest, including:

- Time to travel between clue locations
- Time to solve each clue/puzzle
- Time for any required activities or interactions
- Buffer time for groups that might need extra help

**Duration Guidelines:**
- **Short quests (city blocks/small areas)**: 30-60 minutes
- **Medium quests (neighborhoods/districts)**: 60-120 minutes  
- **Long quests (multiple areas/complex puzzles)**: 120-180 minutes
- **Epic quests (full city experiences)**: 180+ minutes

**Example addition to JSON structure:**
```json
{
  "hunt_code": "BROADBEACH001",
  "title": "Broadbeach Coastal Quest",
  "duration_minutes": 90,
  "medal_image_url": "https://example.com/path/to/medal.png",
  "hunt_name": "Discover Broadbeach's Hidden Gems",
  ...
}
```

**Consider these factors when estimating:**
- Walking/transport time between locations (assume walking pace of 3-4 km/hour)
- Puzzle complexity (simple riddles: 2-5 min, complex puzzles: 5-15 min)
- Photo/activity tasks: 3-8 minutes each
- Group discussion and decision-making time
- Potential for getting lost or needing to backtrack

Please ensure the duration is realistic and accounts for the actual physical distances between clue locations and the complexity of the puzzles you create.

## OPTIONAL ADDITION - Medal Image:

You may also include a `medal_image_url` field pointing to a completion medal image. This should be a publicly accessible image URL (JPG, PNG, GIF, or WebP) that represents the achievement badge players receive upon quest completion.

**Example medal image URL:**
```json
"medal_image_url": "https://example.com/medals/broadbeach-explorer-medal.png"
```

**Note:** Medal images can also be uploaded directly through the Quest Management interface after import.

---

**This addendum ensures your generated quests will have appropriate duration estimates and optional medal images that can be displayed in the Quest Management interface.**
