import unittest

from engine import IntelligenceEngine


class EngineTest(unittest.TestCase):
    def setUp(self):
        self.engine = IntelligenceEngine()
        self.graph = {
            "metrics": {
                "registrations": 100,
                "confirmed": 90,
                "checked_in": 50,
                "paid_revenue": 100000,
                "payment_failure_rate": 0.1,
                "pending_approvals": 20,
                "registration_trend": [8, 10, 11, 13, 14, 18],
                "checkin_rate": 0.75,
                "event_capacity": 500,
                "zone_occupancy": {"vip": 95},
            },
            "nodes": {
                "attendees": [{"id": "A1", "name": "A", "status": "Confirmed", "category": "VIP"}],
                "sessions": [{"id": "S1", "title": "Keynote", "track": "Main"}],
                "exhibitors": [{"id": "E1", "name": "Nova", "booth": "A1"}],
                "leads": [{"id": "L1", "exhibitor_id": "E1", "attendee_id": "A1", "score": 70, "intent": "hot", "notes": "follow up"}],
                "zones": [{"id": "vip", "name": "VIP", "capacity": 100}],
            },
            "edges": [{"type": "meeting", "from": "E1", "to": "A1"}],
        }

    def test_analyze(self):
        result = self.engine.analyze(self.graph)
        self.assertGreaterEqual(result["forecast"]["registrations_7d"], 100)
        self.assertEqual(result["lead_scores"][0]["ai_band"], "hot")
        self.assertEqual(result["crowd"][0]["risk"], "critical")
        self.assertTrue(result["insights"])

    def test_copilot(self):
        analysis = self.engine.analyze(self.graph)
        result = self.engine.copilot("How are registrations doing?", self.graph, analysis)
        self.assertIn("registrations", result["answer"].lower())

    def test_event_blueprint(self):
        result = self.engine.event_blueprint("Create a hybrid expo for VIP sponsors exhibitors with approval")
        self.assertEqual(result["type"], "Expo")
        self.assertEqual(result["format"], "hybrid")
        self.assertEqual(result["registration"]["approval_mode"], "manual")
        self.assertIn("VIP", result["registration"]["categories"])

    def test_concierge(self):
        analysis = self.engine.analyze(self.graph)
        result = self.engine.concierge("What sessions should I attend?", "A1", self.graph, analysis)
        self.assertIn("Keynote", result["answer"])


if __name__ == "__main__":
    unittest.main()
