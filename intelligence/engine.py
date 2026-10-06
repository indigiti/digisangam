from __future__ import annotations

from collections import Counter, defaultdict
from datetime import datetime
from math import sqrt
from statistics import mean
from typing import Any


class IntelligenceEngine:
    def analyze(self, graph: dict[str, Any]) -> dict[str, Any]:
        metrics = graph.get("metrics", {})
        nodes = graph.get("nodes", {})
        edges = graph.get("edges", [])

        lead_scores = self.score_leads(nodes.get("leads", []), edges)
        forecast = self.forecast(metrics)
        anomalies = self.anomalies(metrics)
        recommendations = self.recommendations(nodes, edges)
        crowd = self.crowd(nodes.get("zones", []), metrics.get("zone_occupancy", {}))
        insights = self.insights(metrics, forecast, anomalies, crowd, lead_scores)

        return {
            "generated_at": datetime.utcnow().isoformat(timespec="seconds") + "Z",
            "forecast": forecast,
            "anomalies": anomalies,
            "lead_scores": lead_scores,
            "recommendations": recommendations,
            "crowd": crowd,
            "insights": insights,
        }

    def forecast(self, metrics: dict[str, Any]) -> dict[str, Any]:
        trend = [float(x) for x in metrics.get("registration_trend", [])]
        current = int(metrics.get("registrations", 0))
        capacity = int(metrics.get("event_capacity", 0))

        if not trend:
            projected = current
            velocity = 0.0
        elif len(trend) == 1:
            velocity = trend[-1]
            projected = max(current, int(round(current + velocity)))
        else:
            x = list(range(len(trend)))
            x_mean, y_mean = mean(x), mean(trend)
            denominator = sum((i - x_mean) ** 2 for i in x) or 1.0
            slope = sum((i - x_mean) * (y - y_mean) for i, y in zip(x, trend)) / denominator
            velocity = slope
            projected = max(current, int(round(current + max(0.0, slope) * 7)))

        projected = min(projected, capacity) if capacity > 0 else projected
        checkin_rate = float(metrics.get("checkin_rate", 0.0))
        expected_footfall = int(round(projected * checkin_rate)) if checkin_rate > 0 else int(metrics.get("checked_in", 0))

        return {
            "registrations_7d": projected,
            "daily_velocity": round(velocity, 2),
            "expected_footfall": expected_footfall,
            "confidence": self._confidence(len(trend)),
        }

    def anomalies(self, metrics: dict[str, Any]) -> list[dict[str, Any]]:
        out: list[dict[str, Any]] = []
        trend = [float(x) for x in metrics.get("registration_trend", [])]
        if len(trend) >= 4:
            baseline = trend[:-1]
            sigma = self._stddev(baseline)
            if sigma > 0:
                z = (trend[-1] - mean(baseline)) / sigma
                if abs(z) >= 2:
                    out.append({
                        "type": "registration_velocity",
                        "severity": "high" if abs(z) >= 3 else "medium",
                        "message": "Registration activity is materially outside its recent baseline.",
                        "score": round(z, 2),
                    })

        failure_rate = float(metrics.get("payment_failure_rate", 0.0))
        if failure_rate >= 0.08:
            out.append({
                "type": "payment_failures",
                "severity": "high" if failure_rate >= 0.15 else "medium",
                "message": f"Payment failure rate is {failure_rate * 100:.1f}%.",
                "score": round(failure_rate, 3),
            })

        pending = int(metrics.get("pending_approvals", 0))
        registrations = max(1, int(metrics.get("registrations", 0)))
        if pending / registrations >= 0.12:
            out.append({
                "type": "approval_backlog",
                "severity": "medium",
                "message": f"{pending} registrations are waiting for approval.",
                "score": round(pending / registrations, 3),
            })

        return out

    def score_leads(self, leads: list[dict[str, Any]], edges: list[dict[str, Any]]) -> list[dict[str, Any]]:
        meeting_counts = Counter()
        for edge in edges:
            if edge.get("type") == "meeting":
                meeting_counts[(edge.get("from"), edge.get("to"))] += 1

        scored = []
        for lead in leads:
            base = int(lead.get("score", 50))
            intent_bonus = {"hot": 22, "warm": 10, "low": -8}.get(str(lead.get("intent", "")).lower(), 0)
            meetings = meeting_counts[(lead.get("exhibitor_id"), lead.get("attendee_id"))]
            notes_bonus = 4 if str(lead.get("notes", "")).strip() else 0
            score = max(0, min(100, base + intent_bonus + min(15, meetings * 8) + notes_bonus))
            band = "hot" if score >= 80 else "warm" if score >= 55 else "low"
            scored.append({
                **lead,
                "ai_score": score,
                "ai_band": band,
                "signals": {
                    "intent": lead.get("intent", ""),
                    "meetings": meetings,
                    "has_notes": bool(str(lead.get("notes", "")).strip()),
                },
            })
        return sorted(scored, key=lambda x: x["ai_score"], reverse=True)

    def recommendations(self, nodes: dict[str, Any], edges: list[dict[str, Any]]) -> dict[str, Any]:
        sessions = nodes.get("sessions", [])
        attendees = nodes.get("attendees", [])
        exhibitors = nodes.get("exhibitors", [])
        by_attendee: dict[str, dict[str, Any]] = {}

        for attendee in attendees:
            category = str(attendee.get("category", "General")).lower()
            preferred_tracks = {
                "vip": ["Main", "Innovation"],
                "speaker": ["Main"],
                "sponsor": ["Business", "Innovation", "Main"],
                "media": ["Main", "Innovation"],
            }.get(category, ["Main", "Innovation"])

            session_matches = [s for s in sessions if s.get("track") in preferred_tracks][:4]
            exhibitor_matches = exhibitors[:3]
            by_attendee[str(attendee.get("id", ""))] = {
                "sessions": [{"id": s.get("id"), "title": s.get("title"), "track": s.get("track")} for s in session_matches],
                "exhibitors": [{"id": e.get("id"), "name": e.get("name"), "booth": e.get("booth")} for e in exhibitor_matches],
            }

        return {"attendees": by_attendee}

    def crowd(self, zones: list[dict[str, Any]], occupancy: dict[str, Any]) -> list[dict[str, Any]]:
        result = []
        for zone in zones:
            zone_id = str(zone.get("id", ""))
            capacity = max(0, int(zone.get("capacity", 0)))
            current = max(0, int(occupancy.get(zone_id, 0)))
            ratio = (current / capacity) if capacity else 0.0
            risk = "critical" if ratio >= 0.95 else "high" if ratio >= 0.8 else "moderate" if ratio >= 0.6 else "normal"
            result.append({
                "zone_id": zone_id,
                "name": zone.get("name", zone_id),
                "capacity": capacity,
                "occupancy": current,
                "occupancy_pct": round(ratio * 100, 1),
                "risk": risk,
                "action": self._crowd_action(risk),
            })
        return result

    def insights(
        self,
        metrics: dict[str, Any],
        forecast: dict[str, Any],
        anomalies: list[dict[str, Any]],
        crowd: list[dict[str, Any]],
        lead_scores: list[dict[str, Any]],
    ) -> list[dict[str, Any]]:
        items: list[dict[str, Any]] = []
        if forecast.get("daily_velocity", 0) > 0:
            items.append({
                "priority": "info",
                "title": "Registration momentum",
                "message": f"Projected registrations in 7 days: {forecast['registrations_7d']}.",
            })
        hot_leads = sum(1 for x in lead_scores if x.get("ai_band") == "hot")
        if hot_leads:
            items.append({
                "priority": "high",
                "title": "High-intent sponsor leads",
                "message": f"{hot_leads} leads are currently ranked hot and should be followed up.",
            })
        for zone in crowd:
            if zone["risk"] in {"high", "critical"}:
                items.append({
                    "priority": "critical" if zone["risk"] == "critical" else "high",
                    "title": f"{zone['name']} congestion",
                    "message": f"{zone['occupancy_pct']}% of configured capacity is occupied. {zone['action']}",
                })
        for anomaly in anomalies[:3]:
            items.append({
                "priority": anomaly["severity"],
                "title": anomaly["type"].replace("_", " ").title(),
                "message": anomaly["message"],
            })
        if not items:
            items.append({
                "priority": "info",
                "title": "Operations stable",
                "message": "No material operational anomalies are currently detected.",
            })
        return items[:8]

    def copilot(self, question: str, graph: dict[str, Any], analysis: dict[str, Any]) -> dict[str, Any]:
        q = question.lower().strip()
        metrics = graph.get("metrics", {})
        if any(k in q for k in ("registration", "signup", "booking")):
            answer = (
                f"There are {metrics.get('registrations', 0)} registrations, "
                f"{metrics.get('confirmed', 0)} confirmed, and the 7-day projection is "
                f"{analysis.get('forecast', {}).get('registrations_7d', metrics.get('registrations', 0))}."
            )
        elif any(k in q for k in ("revenue", "payment", "sales")):
            answer = (
                f"Recorded paid revenue is ₹{metrics.get('paid_revenue', 0):,.0f}. "
                f"Payment failure rate is {float(metrics.get('payment_failure_rate', 0)) * 100:.1f}%."
            )
        elif any(k in q for k in ("crowd", "gate", "zone", "capacity")):
            risky = [x for x in analysis.get("crowd", []) if x.get("risk") in {"high", "critical"}]
            answer = "No zones are currently above 80% configured capacity." if not risky else "; ".join(
                f"{x['name']} is at {x['occupancy_pct']}% ({x['risk']}). {x['action']}" for x in risky[:3]
            )
        elif any(k in q for k in ("lead", "sponsor", "exhibitor")):
            hot = [x for x in analysis.get("lead_scores", []) if x.get("ai_band") == "hot"]
            answer = f"{len(hot)} leads are ranked hot. " + (
                "Top lead: " + str(hot[0].get("id", "unknown")) + f" at score {hot[0].get('ai_score')}." if hot else
                "Capture more booth interactions and meetings to improve lead scoring."
            )
        elif any(k in q for k in ("problem", "risk", "anomaly", "attention")):
            insights = analysis.get("insights", [])
            answer = " ".join(x.get("message", "") for x in insights[:3])
        else:
            answer = (
                f"Event status: {metrics.get('registrations', 0)} registrations, "
                f"{metrics.get('checked_in', 0)} checked in, "
                f"{len(analysis.get('anomalies', []))} detected anomalies, and "
                f"{sum(1 for x in analysis.get('lead_scores', []) if x.get('ai_band') == 'hot')} hot leads."
            )

        return {
            "answer": answer,
            "evidence": {
                "metrics": metrics,
                "generated_at": analysis.get("generated_at"),
            },
            "suggested_actions": self._suggested_actions(analysis),
        }

    def concierge(self, question: str, attendee_id: str, graph: dict[str, Any], analysis: dict[str, Any]) -> dict[str, Any]:
        recs = analysis.get("recommendations", {}).get("attendees", {}).get(attendee_id, {})
        q = question.lower().strip()
        attendee = next((x for x in graph.get("nodes", {}).get("attendees", []) if str(x.get("id")) == attendee_id), None)

        if attendee is None:
            return {"answer": "I could not find this attendee profile.", "recommendations": recs}

        if any(k in q for k in ("session", "agenda", "what should i attend")):
            sessions = recs.get("sessions", [])
            answer = "Recommended sessions: " + ", ".join(x.get("title", "") for x in sessions[:3]) if sessions else "No session recommendations are available yet."
        elif any(k in q for k in ("exhibitor", "booth", "company")):
            exhibitors = recs.get("exhibitors", [])
            answer = "Suggested exhibitors: " + ", ".join(f"{x.get('name')} ({x.get('booth') or 'booth TBA'})" for x in exhibitors[:3]) if exhibitors else "No exhibitor recommendations are available yet."
        elif any(k in q for k in ("ticket", "entry", "qr", "access")):
            answer = f"Your registration status is {attendee.get('status', 'Unknown')} and category is {attendee.get('category', 'General')}."
        else:
            answer = f"Welcome {attendee.get('name', '')}. I can help with sessions, exhibitors, access and event navigation."

        return {"answer": answer, "recommendations": recs}

    @staticmethod
    def _stddev(values: list[float]) -> float:
        if len(values) < 2:
            return 0.0
        avg = mean(values)
        return sqrt(sum((x - avg) ** 2 for x in values) / len(values))

    @staticmethod
    def _confidence(samples: int) -> str:
        return "high" if samples >= 10 else "medium" if samples >= 5 else "low"

    @staticmethod
    def _crowd_action(risk: str) -> str:
        return {
            "critical": "Stop additional entry and redirect attendees to another zone.",
            "high": "Open an alternate gate or redirect traffic.",
            "moderate": "Monitor entry rate and prepare an alternate route.",
            "normal": "No intervention required.",
        }[risk]

    @staticmethod
    def _suggested_actions(analysis: dict[str, Any]) -> list[str]:
        actions: list[str] = []
        for zone in analysis.get("crowd", []):
            if zone.get("risk") in {"high", "critical"}:
                actions.append(zone.get("action", "Review zone capacity."))
        if any(x.get("ai_band") == "hot" for x in analysis.get("lead_scores", [])):
            actions.append("Prioritize follow-up with hot exhibitor leads.")
        if analysis.get("anomalies"):
            actions.append("Review detected operational anomalies.")
        return actions[:4]
